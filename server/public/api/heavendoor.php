<?php
require dirname(__FILE__) .'/../../vendor/autoload.php'; // Composerでインストールしたライブラリを読み込む

use Jsonpost\utils\ecdsasigner\EcdsaSignerLite;
/**
 * 初期状態のデータベースを作成します。１度だけ実行する必要があります。
 */


// use JsonPost;
use Jsonpost\Config;
use Jsonpost\responsebuilder\{IResponseBuilder,ErrorResponseBuilder};
use Jsonpost\utils\ecdsasigner\{EasyEcdsaStreamBuilderLite};
use Jsonpost\db\tables\nst2024\{PropertiesTable,DbSpecTable};
use Jsonpost\db\tables\{JsonStorageHistory,JsonStorage,EcdasSignedAccountRoot};
use Jsonpost\db\views\{JsonStorageView};




class SuccessResponseBuilder implements IResponseBuilder {
    private string $godkey;
    private array $params;
    public function __construct($godkey,$params) {
        $this->godkey = $godkey;
        $this->params=$params;
    }

    public function sendResponse() {
        header('Content-Type: application/json');
        http_response_code(200);
        
        echo json_encode([
            'success' => true,
            'result'=>['godkey'=> $this->godkey],
            'params'=>$this->params,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}




function apiMain($db,$request): IResponseBuilder
{
    $version = $request['version'] ?? null;
    $signature = $request['signature'] ?? null;
    $params = $request['params'] ?? null;
    if (!$version || !$signature || !$params) {
        throw new ErrorResponseBuilder("Invalid input parameters.");
    }
    //versionチェック
    if($version!="urn::nyatla.jp:json-request::ecdas-signed-konnichiwa:1"){
        throw new ErrorResponseBuilder("Invalid version");
    }
    $pow_bits_read=isset($params['pow_bits_read'])?intval($params['pow_bits_read']):0;
    $pow_bits_write=isset($params['pow_bits_write'])?intval($params['pow_bits_write']):0;

    //署名を受け取る。ペイロードは"hello_jsonpost"
    $ees=null;
    try{
        #署名からリカバリキーとnonceを取得
        $ees=EasyEcdsaStreamBuilderLite::decode($signature);
        if($ees->data!==bin2hex('konnichiwa')){
            throw new Exception();
        }
    } catch (Exception $e) {
        throw new ErrorResponseBuilder( $e->getMessage());
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
    $t1->insert('god',$ees->pubkey);
    $t1->insert('pow_bits_read',$pow_bits_read);
    $t1->insert('pow_bits_write',$pow_bits_write);

    $t2->insert(JsonStorage::VERSION, $t3->name);
    $t2->insert(EcdasSignedAccountRoot::VERSION,$t4->name);
    $t2->insert(JsonStorageHistory::VERSION,$t5->name);

    $v=new JsonStorageView($db);
    $v->createView();

    return new SuccessResponseBuilder($ees->pubkey,["pow_bits_read"=>$pow_bits_read,"pow_bits_write"=>$pow_bits_write]);

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
    // (new ErrorResponseBuilder($e->getMessage()))->sendResponse();
    (new ErrorResponseBuilder('Internal Error.'))->sendResponse();
}

