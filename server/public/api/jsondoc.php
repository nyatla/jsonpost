<?php

use Jsonpost\UuidWrapper;
require_once dirname(__FILE__).'/../../src/config.php';
require_once dirname(__FILE__).'/../../src/db/tables.php';
require_once dirname(__FILE__).'/../../src/db/views/JsonStorageView.php';
require_once dirname(__FILE__).'/../../src/utils.php';
require_once dirname(__FILE__).'/../../src/response_builder.php';
require_once dirname(__FILE__).'/../../src/db/views/JsonStorageView.php';

// use JsonPost;
use Jsonpost\{
    // EcdasSignedAccountRoot,EasyEcdsaStreamBuilderLite,
    JsonStorage,JsonStorageHistory,JsonStorageView,PropertiesTable,DbSpecTable,
    Config,
    IResponseBuilder,ErrorResponseBuilder};


/**
 * このAPIはjsonstorageの検索APIです。
 */

class SuccessResponseBuilder implements IResponseBuilder {
    private string $path;
    private string $json;    
    public function __construct($path,$json) {
        $this->path=$path;
        $this->json=$json;
    }

    public function sendResponse() {
        header('Content-Type: application/json');
        http_response_code(200);
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

function apiIndexMain($db,$uuid): IResponseBuilder
{
    $v=new JsonStorage($db);
    $ret=$v->selectByUuid($uuid);
    return new SuccessResponseBuilder( '$',$ret['json']);
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
    $ret=apiIndexMain($db,UuidWrapper::text2bin($_GET['uuid']),$limit);
    $ret->sendResponse();
}catch(ErrorResponseBuilder $e){
    $e->sendResponse();
}catch (Exception $e) {
    (new ErrorResponseBuilder($e->getMessage()))->sendResponse();
}catch(Error $e){
    (new ErrorResponseBuilder($e->getMessage()))->sendResponse();
    // (new ErrorResponseBuilder('Internal Error.'))->sendResponse();
}
