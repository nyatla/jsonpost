<?php

use Jsonpost\Config;

/**
 * 所定の書式に格納したJSONファイルのアップロードを受け付けます。
 * 
 */
require_once (dirname(__FILE__) ."/../../src/config.php");
require_once (dirname(__FILE__) ."/../../src/db/tables.php");
require_once (dirname(__FILE__) ."/../../src/utils.php");
require_once (dirname(__FILE__) ."/../../src/response_builder.php");

use Jsonpost\{
    IResponseBuilder,ErrorResponseBuilder,
    EasyEcdsaStreamBuilderLite,EcdasSignedAccountRoot,UuidWrapper,
    JsonStorage,JsonStorageHistory};

class SuccessResponseBuilder implements IResponseBuilder {
    private string $usr_uuid;
    private string $doc_uuid;
    
    public function __construct(string $usr_uuid,string $doc_uuid) {
        $this->doc_uuid=$doc_uuid;
        $this->usr_uuid=$usr_uuid;
    }

    public function sendResponse() {
        header('Content-Type: application/json');
        http_response_code(200);
        
        echo json_encode([
            'success' => true,
            'result'=>[
                'status'=>'created',
                'user'=>[
                    'uuid'=>$this->usr_uuid
                ],
                'document'=>[
                    'uuid'=>$this->doc_uuid
                ]
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}

// アップロードAPIの処理
function apiMain($db,$request):IResponseBuilder
{
    // 1. リクエストの検証
    $version = $request['version'] ?? null;
    $signature = $request['signature'] ?? null;
    $jsonData = $request['data'] ?? null;

    if (!$version || !$signature || !$jsonData) {
        throw new ErrorResponseBuilder("Invalid input parameters.");
    }
    //versionチェック
    if($version!="urn::nyatla.jp:json-request::ecdas-signed-upload:1"){
        throw new ErrorResponseBuilder("Invalid version");
    }

    $pubkey=null;
    $payload=null;
    try{
        #署名からリカバリキーとnonceを取得
        [$pubkey,$payload]=EasyEcdsaStreamBuilderLite::decode($signature);
    } catch (Exception $e) {
        throw new ErrorResponseBuilder( "Invalid signature");
    }
    //ここから書込み系の
    $ar_tbl=new EcdasSignedAccountRoot($db);
    $ar_rec=$ar_tbl->selectOrInsertIfNotExist(hex2bin($pubkey));
    #初めての場合はnonce=0
    $nonce=unpack('N', hex2bin(substr($payload,0,2*4)))[1];
    if($ar_rec['nonce']>=$nonce){
        throw new ErrorResponseBuilder("Invalid nonce. Current nonce={$ar_rec['nonce']}");
    }
    #文章を登録
    $js_tbl=new JsonStorage($db);
    $js_rec=$js_tbl->selectOrInsertIfNotExist(json_encode($jsonData,JSON_UNESCAPED_UNICODE));
    $uh_tbl=new JsonStorageHistory($db);
    $uh_tbl->insert($ar_rec['id'],$js_rec['id']);
    #nonce更新
    $ar_tbl->updateNonce($ar_rec['id'],$nonce);
    $u1=UuidWrapper::loadFromBytes($ar_rec['uuid']);
    $u2=UuidWrapper::loadFromBytes($js_rec['uuid']);
    return new SuccessResponseBuilder($u1->asText(),$u2->asText());
}

$db = Config::getRootDb();//new PDO('sqlite:benchmark_data.db');

$db->exec('BEGIN IMMEDIATE');
try{
    //前処理
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new ErrorResponseBuilder("Method Not Allowed",405);
    }
    
    // POSTリクエストからJSONデータを取得
    $rawData = file_get_contents('php://input');
    // $rawData = file_get_contents('./upload_test.json');
    
    if(strlen($rawData)>Config::MAX_JSON_SIZE){
        throw new ErrorResponseBuilder("upload data too large.");
    }
    // JSONデータのデコード
    $request = json_decode($rawData, true);
    // JSONが無効であればエラーメッセージを返す
    if ($request === null) {
        throw new ErrorResponseBuilder("Invalid JSON format.");
    }
    // アップロードAPI処理を呼び出す
    apiMain($db, $request)->sendResponse();
    $db->exec("COMMIT");
}catch(ErrorResponseBuilder $exception){
    $db->exec("ROLLBACK");
    $exception->sendResponse();
}catch(Exception $exception){
    $db->exec("ROLLBACK");
    (new ErrorResponseBuilder("Internal Error"))->sendResponse();
}catch(Error $e){
    (new ErrorResponseBuilder('Internal Error.'))->sendResponse();
}



