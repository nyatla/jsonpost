<?php
require dirname(__FILE__) .'/../../vendor/autoload.php'; // Composerでインストールしたライブラリを読み込む

use Jsonpost\utils\ecdsasigner\EcdsaSignerLite;
/**
 * 初期状態のデータベースを作成します。１度だけ実行する必要があります。
 */


// use JsonPost;
use Jsonpost\Config;
use Jsonpost\responsebuilder\{IResponseBuilder,ErrorResponseBuilder,SuccessResultResponseBuilder};
use Jsonpost\db\views\{JsonStorageView};
use Jsonpost\utils\{UuidWrapper};




function apiIndexMain($db,$index,$limit,$filter,$value): IResponseBuilder
{
    $v=new JsonStorageView($db);
    $ret=$v->selectByIndexWithFilter($index, $limit,$filter,$value);
    $nitems=[];
    foreach($ret['items'] as $i) {
        $nitems[]=[$i[1],UuidWrapper::bin2text($i[2]),bin2hex($i[3])];
    }

    return new SuccessResultResponseBuilder(
        ['total'=>$ret['total'],'items'=>$nitems]
    );
}


function apiIndexMain2($db,$uuid,$limit,$filter,$value): IResponseBuilder
{
    //排他キーのチェック
    $v=new JsonStorageView($db);
    $ret=$v->selectByUuid($uuid, $limit,$filter,$value);
    $nitems=[];
    foreach($ret['items'] as $i) {
        $nitems[]=[$i[1],UuidWrapper::bin2text($i[2]),bin2hex($i[3])];
    }
    return new SuccessResultResponseBuilder(
        ['total'=>$ret['total'],'items'=>$nitems]
    );
}







// SQLiteデータベースに接続
$db = Config::getRootDb();//new PDO('sqlite:benchmark_data.db');
try{
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ErrorResponseBuilder::throwResponse(101,status:405);
    }
    
    $limit=100;
    if(isset($_GET['limit'])){
        $limit=intval($_GET['limit']);
    }
    //排他キーのチェック
    $index=0;
    $keys=['index', 'page', 'uuid'];
    $count = count(array_filter($keys, fn($p) => isset($_GET[$p])));
    if ($count > 1) {
        ErrorResponseBuilder::throwResponse(103,'Please set one of '.implode(',', $keys),400);
    }
    $path=$_GET['path'] ?? null;
    $value=$_GET['value'] ?? null;
    $ret;
    if($count==0){
        $ret=apiIndexMain($db,0,$limit,$path,$value);
    }else if(isset($_GET['index'])){
        $index=intval($_GET['index']);
        $ret=apiIndexMain($db,$index,$limit,$path,$value);
    }else if(isset($_GET['page'])){
        $index=intval($_GET['page'])*$limit;
        $ret=apiIndexMain($db,$index,$limit,$path,$value);
    }else if (isset($_GET['uuid'])){
        $uuid=UuidWrapper::text2bin($_GET['uuid']);
        $ret=apiIndexMain2($db,$uuid,$limit,$path,$value);

    }
    $ret->sendResponse();
}catch(ErrorResponseBuilder $e){
    $e->sendResponse();
}catch (Exception $e) {
    ErrorResponseBuilder::catchException($e)->sendResponse();
}catch(Error $e){
    ErrorResponseBuilder::catchException($e)->sendResponse();
}

