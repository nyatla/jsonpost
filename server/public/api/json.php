<?php
require dirname(__FILE__) .'/../../vendor/autoload.php'; // Composerでインストールしたライブラリを読み込む

use Jsonpost\utils\ecdsasigner\EcdsaSignerLite;
/**
 * 初期状態のデータベースを作成します。１度だけ実行する必要があります。
 */


// use JsonPost;
use Jsonpost\Config;
use Jsonpost\responsebuilder\{IResponseBuilder,ErrorResponseBuilder,SuccessResultResponseBuilder};
use Jsonpost\db\tables\{JsonStorageHistory,JsonStorage,EcdasSignedAccountRoot};
use Jsonpost\utils\{UuidWrapper};
use JsonPath\{JsonPath,JsonPathException};

/**
 * このAPIはjsonstorageの検索APIです。
 */

class RawJsonResponseBuilder implements IResponseBuilder {
    private string $json;
    public function __construct($json) {
        $this->json=$json;
    }

    public function sendResponse() {
        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode($this->json, JSON_UNESCAPED_UNICODE);
    }
}

function apiIndexMain($db,$uuid,$path,$is_raw): IResponseBuilder
{
    $v=new JsonStorage($db);
    $ret=$v->selectByUuid($uuid);
    $found=json_decode($ret->json,true);
    if(isset($path)){
        $jp=new JsonPath();
        $found=$jp->find($found,$path);    
    }

    

    if(!$is_raw){
        return new SuccessResultResponseBuilder(['path'=> '$','data'=>$found]);
    }else{
        return new RawJsonResponseBuilder($found);
    }
}


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    (new ErrorResponseBuilder('Method Not Allowed',405))->sendResponse();
}

// SQLiteデータベースに接続
$db = Config::getRootDb();//new PDO('sqlite:benchmark_data.db');
try{
    if(!isset($_GET['uuid'])){
        throw new ErrorResponseBuilder("Invalid query",405);
    }
    $ret=apiIndexMain(
        $db,
        UuidWrapper::text2bin($_GET['uuid']),
        $_GET['path']??null,
        isset($_GET['raw']),
    );
    $ret->sendResponse();
}catch(ErrorResponseBuilder $e){
    $e->sendResponse();
}catch (Exception $e) {
    (new ErrorResponseBuilder($e->getMessage()))->sendResponse();
}catch(Error $e){
    (new ErrorResponseBuilder($e->getMessage()))->sendResponse();
    // (new ErrorResponseBuilder('Internal Error.'))->sendResponse();
}
