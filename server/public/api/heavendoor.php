<?php
/**
 * 初期状態のデータベースを作成します。１度だけ実行する必要があります。
 */
require_once dirname(__FILE__).'/../../src/config.php';
require_once dirname(__FILE__).'/../../src/db/tables.php';
require_once dirname(__FILE__).'/../../src/utils.php';
require_once dirname(__FILE__).'/../../src/response_builder.php';
require_once dirname(__FILE__).'/../../src/db/views/JsonStorageView.php';

// use JsonPost;
use Jsonpost\{
    EcdasSignedAccountRoot,EasyEcdsaStreamBuilderLite,
    JsonStorage,JsonStorageHistory,PropertiesTable,DbSpecTable,JsonStorageView,
    Config,IResponseBuilder,ErrorResponseBuilder};


class SuccessResponseBuilder implements IResponseBuilder {
    private string $godkey;
    public function __construct($godkey) {
        $this->godkey = $godkey;
    }

    public function sendResponse() {
        header('Content-Type: application/json');
        http_response_code(200);
        
        echo json_encode([
            'success' => true,
            'result'=>["godkey"=> $this->godkey],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}




function apiMain($db,$request): IResponseBuilder
{
    $version = $request['version'] ?? null;
    $signature = $request['signature'] ?? null;
    if (!$version || !$signature) {
        throw new ErrorResponseBuilder("Invalid input parameters.");
    }
    //versionチェック
    if($version!="urn::nyatla.jp:json-request::ecdas-signed-konnichiwa:1"){
        throw new ErrorResponseBuilder("Invalid version");
    }

    //署名を受け取る。ペイロードは"hello_jsonpost"
    $pubkey=null;
    $payload=null;
    try{
        #署名からリカバリキーとnonceを取得
        [$pubkey,$payload]=EasyEcdsaStreamBuilderLite::decode($signature);
        if(hex2bin($payload)!=='konnichiwa'){
            throw new Exception();
        }
    } catch (Exception $e) {
        throw new ErrorResponseBuilder( "Invalid signature");
    } 

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
    $t1->insert('god',$pubkey);

    $t2->insert(JsonStorage::VERSION, $t3->name);
    $t2->insert(EcdasSignedAccountRoot::VERSION,$t4->name);
    $t2->insert(JsonStorageHistory::VERSION,$t5->name);

    $v=new JsonStorageView($db);
    $v->createView();

    return new SuccessResponseBuilder($pubkey);

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
            // JSONデータのデコード
            $request = json_decode($rawData, true);
            // JSONが無効であればエラーメッセージを返す
            if ($request === null) {
                throw new ErrorResponseBuilder("Invalid JSON format.");
            }
            // データベースのセットアップ
            
            apiMain($db,$request)->sendResponse();
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
    (new ErrorResponseBuilder('Internal Error.'))->sendResponse();
}

