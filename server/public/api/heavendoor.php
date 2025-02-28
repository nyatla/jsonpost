<?php

use Jsonpost\utils\pow\TimeSizeDifficultyBuilder;

require dirname(__FILE__) .'/../../vendor/autoload.php'; // Composerでインストールしたライブラリを読み込む

use Jsonpost\utils\ecdsasigner\EcdsaSignerLite;
/**
 * 初期状態のデータベースを作成します。１度だけ実行する必要があります。
 */


// use JsonPost;
use Jsonpost\Config;
use Jsonpost\responsebuilder\{IResponseBuilder,ErrorResponseBuilder,SuccessResultResponseBuilder};
use Jsonpost\endpoint\{ZeroStampEndpoint};
use Jsonpost\db\tables\nst2024\{PropertiesTable,DbSpecTable};
use Jsonpost\db\tables\{JsonStorageHistory,JsonStorage,EcdasSignedAccountRoot};
use Jsonpost\db\views\{JsonStorageView};








function apiMain($db,string $rawData): IResponseBuilder
{
    //JSONペイロードの評価
    $request = json_decode($rawData, true);
    if ($request === null) {
        ErrorResponseBuilder::throwResponse(301,'Invalid JSON format.');
    }
    foreach(['version','params']as $v){
        if(!array_key_exists($v,$request)){
            ErrorResponseBuilder::throwResponse(302,"Parameter $.$v not found.");
        }
    } 
    foreach(['server_name']as $v){
        if(!array_key_exists($v,$request['params'])){
            ErrorResponseBuilder::throwResponse(302,"Parameter $.params.$v not found.");
        }
    } 
    $version = $request['version'];
    $params=$request['params'];
    if($version!="urn::nyatla.jp:json-request::ecdas-signed-konnichiwa:1"){
        ErrorResponseBuilder::throwResponse(303,"Parameter $.params.$v not found.");
    }
    $algorithm_name=$params[PropertiesTable::VNAME_POW_ALGORITHM] ??null;
    if(!$algorithm_name){
        ErrorResponseBuilder::throwResponse(302,'Pow algorithm not set.');
    }
    $pow_algorithm=null;
    try{
        $pow_algorithm=TimeSizeDifficultyBuilder::fromText($algorithm_name);
    }catch(Exception $e){
        ErrorResponseBuilder::throwResponse(303,"Unknown algorihm '$algorithm_name'");
    }

    $endpoint=new ZeroStampEndpoint($params['server_name'],$rawData);
    
    $pubkey_hex=bin2hex($endpoint->stamp->getEcdsaPubkey());

    // テーブルがすでに存在するかを確認
    $checkSql = "SELECT name FROM sqlite_master WHERE type='table' AND name='properties';";
    $result = $db->query($checkSql);
    
    // properties テーブルが存在しない場合、初期化を実行
    if ($result->fetch()) {
        ErrorResponseBuilder::throwResponse(501);
    }
    $t1=new PropertiesTable($db);
    $t1->createTable();
    $t3=new JsonStorage($db);
    $t3->createTable();
    $t4=new EcdasSignedAccountRoot($db);
    $t4->createTable();
    $t5=new JsonStorageHistory($db);
    $t5->createTable();

    $t2=new DbSpecTable($db);
    $t2->createTable();

    $t1->insert(PropertiesTable::VNAME_VERSION,Config::VERSION);
    $t1->insert(PropertiesTable::VNAME_GOD,$pubkey_hex);    //管理者キー
    $t1->insert(PropertiesTable::VNAME_SERVER_NAME,$params['server_name']); //サーバー識別名
    $t1->insert(PropertiesTable::VNAME_POW_ALGORITHM,$pow_algorithm->serialize());    //Diffアルゴリズム識別子
    $t1->insert(PropertiesTable::VNAME_ROOT_POW_ACCEPT_TIME,0);     //ルートのPOWリクエスト受理時刻
    

    $t2->insert(JsonStorage::VERSION, $t3->name);
    $t2->insert(EcdasSignedAccountRoot::VERSION,$t4->name);
    $t2->insert(JsonStorageHistory::VERSION,$t5->name);

    $v=new JsonStorageView($db);
    $v->createView();

    return new SuccessResultResponseBuilder(
        [
            PropertiesTable::VNAME_GOD=>$pubkey_hex,
            PropertiesTable::VNAME_SERVER_NAME=>$params['server_name'],
            PropertiesTable::VNAME_POW_ALGORITHM=>$pow_algorithm->serialize(),
        ]);
}





// SQLiteデータベースに接続
$db = Config::getRootDb();//new PDO('sqlite:benchmark_data.db');
$db->exec('BEGIN IMMEDIATE');
try{
    switch($_SERVER['QUERY_STRING']){
        case 'konnichiwa':{
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                ErrorResponseBuilder::throwResponse(101,status:405);
            }
            // POSTリクエストからJSONデータを取得
            $rawData = file_get_contents('php://input');
            // $rawData = file_get_contents('./upload_test.json');
            if(strlen($rawData)>2048){
                ErrorResponseBuilder::throwResponse(301,"upload data too large.",413);
            }
            // データベースのセットアップ            
            apiMain($db,$rawData)->sendResponse();
            break;
        }
        default:{
            ErrorResponseBuilder::throwResponse(101,"The door to heaven is closed. ");
        }
    }
    $db->exec("COMMIT");
}catch(ErrorResponseBuilder $e){
    $db->exec("ROLLBACK");
    $e->sendResponse();
}catch (Exception $e) {
    $db->exec("ROLLBACK");
    ErrorResponseBuilder::catchException($e)->sendResponse();
}catch(Error $e){
    ErrorResponseBuilder::catchException($e)->sendResponse();
}

