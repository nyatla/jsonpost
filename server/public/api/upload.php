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
use Jsonpost\utils\{JCSValidator};

use Opis\JsonSchema\{Validator, Errors\ErrorFormatter};






// アップロードAPIの処理
function upload($db,$rawData):IResponseBuilder
{

    $endpoint=PoWAccountRequiredEndpoint::create($db,$rawData);
    
    if($endpoint->properties->json_jcs){
        $v=new JCSValidator();
        try{
            $v->isJcsToken($rawData);
        }catch(\Exception $e){
            $m=$e->getMessage();
            ErrorResponseBuilder::throwResponse(301,"Not JCS compatible.:$m");
        }
    }
    if($endpoint->properties->json_schema!=null){
        // JSONスキーマとデータをデコード
        $schema = $endpoint->properties->json_schema;
        $data=json_decode($rawData);
        // JSONスキーマの検証
        $validator = new Validator();
        $validator->setStopAtFirstError(true);
        $vret = $validator->validate($data, $schema);
        if ($vret->isValid()=== false) {
            $errors=(new ErrorFormatter())->formatFlat($vret->error());
            $em=$errors[0];
            ErrorResponseBuilder::throwResponse(301,"Json scheam not valid.:$em");
        }
    }else{
        $request = json_decode($rawData, true);
        if ($request === null) {
            ErrorResponseBuilder::throwResponse(301,'Invalid JSON format.');
        }            
    }


    //ここから書込み系の
    $ar_rec=$endpoint->account;

    //アップデートのバッチ処理
    $js_tbl=new JsonStorage($db);
    $hs_tbl=new History($db);
    $jsh_table=new JsonStorageHistory($db);
    $js_rec=$js_tbl->selectOrInsertIfNotExist($rawData);
    // $js_rec=$js_tbl->selectOrInsertIfNotExist($rawData);
    $hs_rec=$hs_tbl->insert($endpoint->accepted_time,$ar_rec->id,$endpoint->stamp->stamp,$endpoint->required_pow);
    $jsh_rec=$jsh_table->insert($hs_rec->id,$js_rec->id);    
    //アップデートのバッチ処理/


    $endpoint->commitStamp();
    $ps=$endpoint->stamp;
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
            'accepted'=>$ps->getPowScore32(),
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
    upload($db, $rawData)->sendResponse();
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



