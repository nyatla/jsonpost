<?php

require_once (dirname(__FILE__) ."/../libs/db/tables.php");
require_once (dirname(__FILE__) ."/../libs/utils.php");
require_once (dirname(__FILE__) ."/../libs/response_builder.php");


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
function uploadAPI($db,$request):IResponseBuilder
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
    $ar_rec=$ar_tbl->selectOrInsertIfNotExist($pubkey);
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
    return new SuccessResponseBuilder($ar_rec['uuid'],$js_rec['uuid']);
}

$db = new PDO('sqlite:benchmark_data.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->beginTransaction();
try{
    //前処理
    
    // POSTリクエストからJSONデータを取得
    $rawData = file_get_contents('php://input');
    // $rawData = file_get_contents('./upload_test.json');
    
    if(strlen($rawData)>1024*256){
        throw new ErrorResponseBuilder("upload data too large.");
    }
    // JSONデータのデコード
    $request = json_decode($rawData, true);
    // JSONが無効であればエラーメッセージを返す
    if ($request === null) {
        throw new ErrorResponseBuilder("Invalid JSON format.");
    }
    // アップロードAPI処理を呼び出す
    uploadAPI($db, $request)->sendResponse();
    $db->commit();
}catch(ErrorResponseBuilder $exception){
    $db->rollBack();
    $exception->sendResponse();
}catch(Exception $exception){
    $db->rollBack();
    (new ErrorResponseBuilder("Internal Error"))->sendResponse();
}



?>