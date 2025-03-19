<?php


require dirname(__FILE__) .'/../../vendor/autoload.php'; // Composerでインストールしたライブラリを読み込む

use Jsonpost\utils\ecdsasigner\EcdsaSignerLite;
/**
 * 初期状態のデータベースを作成します。１度だけ実行する必要があります。
 */


// use JsonPost;
use Jsonpost\db\query\{RawJsonQueryRecord,StorageQueryRecord};
use Jsonpost\Config;
use Jsonpost\responsebuilder\{IResponseBuilder,ErrorResponseBuilder,SuccessResultResponseBuilder};
use Jsonpost\utils\{UuidWrapper};
use JsonPath\{JsonPath};

/**
 * このAPIはjsonstorageの検索APIです。
 */

class RawJsonResponseBuilder implements IResponseBuilder {
    private mixed $json;
    public function __construct($json) {
        $this->json=$json;
    }

    public function sendResponse() {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET");
        header("Access-Control-Allow-Headers: Content-Disposition, Content-Type, Content-Length, Accept-Encoding");        
        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode($this->json, JSON_UNESCAPED_UNICODE);
    }
}

function byOffset($db,$uuid,$path,$is_raw): IResponseBuilder
{
    // $v=new JsonStorage($db);
    // $ret=$v->selectByUuid($uuid);
    // $found=json_decode($ret->json,true);
    // if(isset($path)){
    //     $jp=new JsonPath();
    //     $found=$jp->find($found,$path);    
    // }


    if(!$is_raw){
        $ret=StorageQueryRecord::query($db,$uuid);
        $found=json_decode($ret->json,true);
        if(isset($path)){
            $jp=new JsonPath();
            $found=$jp->find($found,$path);    
        }else{
            $path='$';
        }
        return new SuccessResultResponseBuilder([
            'path'=> $path,
            'timestamp'=> $ret->timestamp,
            'powstamp'=>$ret->powStampAsHex(),
            'uuid_account'=>$ret->uuidAccountAsText(),
            'uuid_document'=>$ret->uuidHistoryAsText(),
            'json'=>$found
        ]);
    }else{
        $ret=RawJsonQueryRecord::query($db,$uuid);
        $found=json_decode($ret->json,true);
        if(isset($path)){
            $jp=new JsonPath();
            $found=$jp->find($found,$path);    
        }else{
            $path='$';
        }
        return new RawJsonResponseBuilder($found);
    }
}



// SQLiteデータベースに接続
$db = Config::getRootDb();//new PDO('sqlite:benchmark_data.db');
try{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ErrorResponseBuilder::throwResponse(101,status:405);
    }
    
    if(!isset($_GET['uuid'])){
        ErrorResponseBuilder::throwResponse(102);
    }
    $ret=byOffset(
        $db,
        UuidWrapper::text2bin($_GET['uuid']),
        $_GET['path']??null,
        isset($_GET['raw']),
    );
    $ret->sendResponse();
}catch(ErrorResponseBuilder $e){
    $e->sendResponse();
}catch (Exception $e) {
    ErrorResponseBuilder::catchException($e)->sendResponse();
}catch(Error $e){
    ErrorResponseBuilder::catchException($e)->sendResponse();
}
