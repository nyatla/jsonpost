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
use Jsonpost\utils\ecdsasigner\{PowStamp};
use Jsonpost\db\tables\nst2024\{PropertiesTable,DbSpecTable};
use Jsonpost\db\tables\{JsonStorageHistory,JsonStorage,EcdasSignedAccountRoot};
use Jsonpost\db\views\{JsonStorageView};









function apiMain($db,string $rawData): IResponseBuilder
{

    $powstamp1 = $_SERVER['HTTP_POWSTAMP_1'] ?? null;
    $request = json_decode($rawData, true);
    if ($request === null) {
        throw new ErrorResponseBuilder("Invalid JSON format.");
    }
    foreach(['version','params']as $v){
        if(!array_key_exists($v,$request)){
            throw new ErrorResponseBuilder("Invalid JSON $.$v not found.");
        }
    } 
    foreach(['server_name']as $v){
        if(!array_key_exists($v,$request['params'])){
            throw new ErrorResponseBuilder("Invalid JSON params. $.params.$v not found.");
        }
    } 
    $version = $request['version'];
    $params=$request['params'];
    if($version!="urn::nyatla.jp:json-request::ecdas-signed-konnichiwa:1"){
        throw new ErrorResponseBuilder("Invalid version");
    }        
    //スタンプの読出し
    $ps=null;
    try{
        $ps=new PowStamp(hex2bin($powstamp1));
        if($ps->getNonceAsInt()!=0){
            throw new ErrorResponseBuilder("Invalid Nonde");
        }
        if(!PowStamp::verify($ps,$params['server_name'],$rawData,0)){
            throw new Exception('PowStamp verify failed');
        }
    } catch (Exception $e) {
        throw new ErrorResponseBuilder( $e->getMessage());
    }
    if(!isset($params[PropertiesTable::VNAME_POW_ALGORITHM])){
        throw new Exception('Pow algorithm not set.');
    }
    $pow_algorithm=TimeSizeDifficultyBuilder::fromText($params[PropertiesTable::VNAME_POW_ALGORITHM]);

    
    $pubkey_hex=bin2hex($ps->getEcdsaPubkey());

    // テーブルがすでに存在するかを確認
    $checkSql = "SELECT name FROM sqlite_master WHERE type='table' AND name='properties';";
    $result = $db->query($checkSql);
    
    // properties テーブルが存在しない場合、初期化を実行
    if ($result->fetch()) {
        throw new ErrorResponseBuilder("Already initialized.");
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

    $t1->insert('version',Config::VERSION);
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
            // POSTリクエストからJSONデータを取得
            $rawData = file_get_contents('php://input');
            // $rawData = file_get_contents('./upload_test.json');
            if(strlen($rawData)>1024){
                throw new ErrorResponseBuilder("upload data too large.");
            }
            // データベースのセットアップ
            
            apiMain($db,$rawData)->sendResponse();
            break;
        }
        default:{
            throw new ErrorResponseBuilder("The door to heaven is closed. ");
        }
    }
    $db->exec("COMMIT");
}catch(ErrorResponseBuilder $e){
    $db->exec("ROLLBACK");
    $e->sendResponse();
}catch (Exception $e) {
    $db->exec("ROLLBACK");
    (new ErrorResponseBuilder($e->getMessage()))->sendResponse();
}catch(Error $e){
    (new ErrorResponseBuilder($e->getMessage()))->sendResponse();
    // (new ErrorResponseBuilder('Internal Error.'))->sendResponse();
}

