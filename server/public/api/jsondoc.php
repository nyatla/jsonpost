<?php
require dirname(__FILE__) .'/../../vendor/autoload.php'; // Composerでインストールしたライブラリを読み込む

use Jsonpost\utils\ecdsasigner\EcdsaSignerLite;
/**
 * 初期状態のデータベースを作成します。１度だけ実行する必要があります。
 */


// use JsonPost;
use Jsonpost\Config;
use Jsonpost\responsebuilder\{IResponseBuilder,ErrorResponseBuilder};
use Jsonpost\db\tables\{JsonStorageHistory,JsonStorage,EcdasSignedAccountRoot};
use Jsonpost\utils\{UuidWrapper};


/**
 * このAPIはjsonstorageの検索APIです。
 */

class SuccessResponseBuilder implements IResponseBuilder {
    private string $path;
    private string $json;
    private string $is_raw;
    public function __construct($path,$json,$is_raw) {
        $this->path=$path;
        $this->json=$json;
        $this->is_raw=$is_raw;
    }

    public function sendResponse() {
        header('Content-Type: application/json');
        http_response_code(200);
        if($this->is_raw) {
            echo json_encode(json_decode($this->json), JSON_UNESCAPED_UNICODE);
        }else{
            echo json_encode([
                'success' => true,
                // 'aa'=>1,
                'result'=>[
                    'path'=> $this->path,
                    'data'=>json_decode($this->json),
                ],
            ], JSON_UNESCAPED_UNICODE);    
        }
    }
}

function apiIndexMain($db,$uuid,$is_raw): IResponseBuilder
{
    $v=new JsonStorage($db);
    $ret=$v->selectByUuid($uuid);
    return new SuccessResponseBuilder( '$',$ret['json'],$is_raw);
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
        isset($_GET['raw']));
    $ret->sendResponse();
}catch(ErrorResponseBuilder $e){
    $e->sendResponse();
}catch (Exception $e) {
    (new ErrorResponseBuilder($e->getMessage()))->sendResponse();
}catch(Error $e){
    (new ErrorResponseBuilder($e->getMessage()))->sendResponse();
    // (new ErrorResponseBuilder('Internal Error.'))->sendResponse();
}
