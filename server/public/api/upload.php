<?php



/**
 * 所定の書式に格納したJSONファイルのアップロードを受け付けます。
 * 
 */
require dirname(__FILE__) .'/../../vendor/autoload.php'; // Composerでインストールしたライブラリを読み込む


// use JsonPost;
use Jsonpost\Config;
use Jsonpost\responsebuilder\{IResponseBuilder,ErrorResponseBuilder,SuccessResultResponseBuilder};
use Jsonpost\endpoint\{PoWAccountRequiredEndpoint};
use Jsonpost\db\tables\{JsonStorageHistory,JsonStorage,History};






// アップロードAPIの処理
function konnichiwa($db,$rawData):IResponseBuilder
{

    $endpoint=PoWAccountRequiredEndpoint::create($db,$rawData);
    $ps=$endpoint->stamp;
    
    //ここから書込み系の
    $ar_rec=$endpoint->account;

    
    $current_score=$ps->getPowScore32();

    #文章を登録
    $request = json_decode($rawData, true);
    if ($request === null) {
        ErrorResponseBuilder::throwResponse(301,'Invalid JSON format.');
    }    
    //アップデートのバッチ処理
    $js_tbl=new JsonStorage($db);
    $hs_tbl=new History($db);
    $jsh_table=new JsonStorageHistory($db);

    $js_rec=$js_tbl->selectOrInsertIfNotExist(json_encode($request,JSON_UNESCAPED_UNICODE));
    $hs_rec=$hs_tbl->insert($endpoint->accepted_time,$ar_rec->id,$endpoint->stamp->getPowScore32(),$endpoint->required_pow);
    $jsh_rec=$jsh_table->insert($hs_rec->id,$js_rec->id);    
    //アップデートのバッチ処理/


    $endpoint->commitStamp();
    return new SuccessResultResponseBuilder(
        [
        'document'=>[
            'status'=>$js_rec->is_new_record?'new':'copy',
            'json_uuid'=>$jsh_rec->uuidAsText(),
        ],
        'account'=>[
            'status'=>$ar_rec->is_new_record?'new':'exist',
            'user_uuid'=>$ar_rec->uuidAsText(),
            'nonce'=>$ps->getNonceAsInt(),    
        ],
        'pow'=>[
            'domain'=>$ar_rec->is_new_record?'root':'account',
            'required'=>$endpoint->required_pow,
            'accepted'=>$current_score,
        ]
        ], JSON_PRETTY_PRINT);
}

$db = Config::getRootDb();//new PDO('sqlite:benchmark_data.db');

$db->exec('BEGIN IMMEDIATE');
try{
    //前処理
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ErrorResponseBuilder::throwResponse(101,status:405);
    }
    
    // POSTリクエストからJSONデータを取得
    $rawData = file_get_contents('php://input');
    // $rawData = file_get_contents('./upload_test.json');
    
    if(strlen($rawData)>Config::MAX_JSON_SIZE){
        ErrorResponseBuilder::throwResponse(304);
    }

    // アップロードAPI処理を呼び出す
    konnichiwa($db, $rawData)->sendResponse();
    $db->exec("COMMIT");
}catch(ErrorResponseBuilder $e){
    $db->exec("ROLLBACK");
    $e->sendResponse();
    throw $e;
}catch(Exception $e){
    $db->exec("ROLLBACK");
    ErrorResponseBuilder::catchException($e)->sendResponse();
    throw $e;
}catch(Error $e){
    $db->exec("ROLLBACK");
    ErrorResponseBuilder::catchException($e)->sendResponse();
    throw $e;
}



