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
    private array $items;
    private int $total;
    private int $index;    
    public function __construct($total,array $items) {
        $this->total=$total;
        $nitems=[];
        foreach($items as $i) {
            $nitems[]=[$i[1],UuidWrapper::bin2text($i[2]),bin2hex($i[3])];
        }



        $this->items=$nitems;
    }

    public function sendResponse() {
        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode([
            'success' => true,
            // 'aa'=>1,
            'result'=>[
                "items"=> $this->items,
                "total"=>$this->total,
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}




function apiIndexMain($db,$index,$limit): IResponseBuilder
{
    $v=new JsonStorageView($db);
    $ret=$v->selectByIndex($index, $limit);
    return new SuccessResponseBuilder( $ret['total'],$ret['items']);
}


function apiIndexMain2($db,$uuid,$limit): IResponseBuilder
{
    //排他キーのチェック
    $v=new JsonStorageView($db);
    $ret=$v->selectByUuid($uuid, $limit);
    return new SuccessResponseBuilder($ret['total'],$ret['items']);
}






if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    (new ErrorResponseBuilder("Method Not Allowed",405))->sendResponse();
}

// SQLiteデータベースに接続
$db = Config::getRootDb();//new PDO('sqlite:benchmark_data.db');
try{
    $limit=100;
    if(isset($_GET['limit'])){
        $limit=intval($_GET['limit']);
    }
    //排他キーのチェック
    $index=0;
    $keys=['index', 'page', 'uuid'];
    $count = count(array_filter($keys, fn($p) => isset($_GET[$p])));
    if ($count > 1) {
        new ErrorResponseBuilder('Please set one of '.implode(',', $keys),405);
    }
    $ret;
    if($count==0){
        $ret=apiIndexMain($db,0,$limit);
    }else if(isset($_GET['index'])){
        $index=intval($_GET['index']);
        $ret=apiIndexMain($db,$index,$limit);
    }else if(isset($_GET['page'])){
        $index=intval($_GET['page'])*$limit;
        $ret=apiIndexMain($db,$index,$limit);
    }else if (isset($_GET['uuid'])){
        $uuid=UuidWrapper::text2bin($_GET['uuid']);
        $ret=apiIndexMain2($db,$uuid,$limit);

    }
    $ret->sendResponse();
}catch(ErrorResponseBuilder $e){
    $e->sendResponse();
}catch (Exception $e) {
    (new ErrorResponseBuilder($e->getMessage()))->sendResponse();
}catch(Error $e){
    (new ErrorResponseBuilder('Internal Error.'))->sendResponse();
}

