<?php

require dirname(__FILE__) .'/../../vendor/autoload.php'; // Composerでインストールしたライブラリを読み込む


/**
 * ドキュメント(storage)の履歴を参照します。
 */


// use JsonPost;
use Jsonpost\Config;
use Jsonpost\db\tables\JsonStorageHistory;
use Jsonpost\responsebuilder\{IResponseBuilder,ErrorResponseBuilder,SuccessResultResponseBuilder};
use Jsonpost\utils\{UuidWrapper};
use Jsonpost\db\query\JsonListQueryRecord;




function byOffset($db,$offset,$limit,$filter,$value): IResponseBuilder
{
    assert($offset>=0);
    assert($limit>=-1);

    $jsh=new JsonStorageHistory($db);
    $total=$jsh->totalCount();
    $nitems=[];
    if($total>0){
        if($offset>=$total){
            ErrorResponseBuilder::throwResponse(103, "Index is too large");
        }
        if($limit==-1){
            $limit=$total-$offset;
        }else{
            $limit=min($total-$offset,$limit);
        }
        $ret=JsonListQueryRecord::query($db,$offset,$limit,$filter,$value);
        foreach($ret as $i) {
            $nitems[]=[
                $i->timestamp,
                $i->uuidHistoryAsText(), //document_uuid
                $i->uuidAccountAsText(), //user_uuid
                $i->size,
                bin2hex($i->hash)];
        }    
    }else{
        //ntd

    }
 

    return new SuccessResultResponseBuilder(
        [
            'total'=>$total,
            'range'=>[
                'offset'=>$offset,
                'limit'=>$limit
            ],
            'table'=>[
                'head'=>['timestamp','uuid_document','uuid_account','size','hash'],
                'rows'=>$nitems
            ]
        ]
    );
}


function byUuid($db,$uuid,$limit,$filter,$value): IResponseBuilder
{
    $jsh=new JsonStorageHistory($db);
    $offset=$jsh->getUuidOffset(UuidWrapper::text2bin($uuid));
    return byOffset($db,$offset,$limit,$filter,$value);
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
    $offset=0;
    $keys=['offset', 'page', 'uuid'];
    $count = count(array_filter($keys, fn($p) => isset($_GET[$p])));
    if ($count > 1) {
        ErrorResponseBuilder::throwResponse(103,'Please set one of '.implode(',', $keys),400);
    }
    $path=$_GET['path'] ?? null;
    $value=$_GET['value'] ?? null;
    $ret;
    if($count==0){
        $ret=byOffset($db,0,100,$path,$value);
    }else if(isset($_GET['offset'])){
        $offset=intval($_GET['offset']);
        $ret=byOffset($db,$offset,$limit,$path,$value);
    }else if(isset($_GET['page'])){
        $offset=intval($_GET['page'])*$limit;
        $ret=byOffset($db,$offset,$limit,$path,$value);
    }else if (isset($_GET['uuid'])){
        $ret=byUuid($db,$_GET['uuid'],$limit,$path,$value);
    }
    $ret->sendResponse();
}catch(ErrorResponseBuilder $e){
    $e->sendResponse();
}catch (Exception $e) {
    ErrorResponseBuilder::catchException($e)->sendResponse();
}catch(Error $e){
    ErrorResponseBuilder::catchException($e)->sendResponse();
}

