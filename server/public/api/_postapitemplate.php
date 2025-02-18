<?php



class SuccessResponseBuilder implements IResponseBuilder {
    private string $godkey;
    public function __construct($godkey) {
        $this->godkey = $godkey;
    }

    public function sendResponse() {
        header('Content-Type: application/json');
        http_response_code(200);
        
        echo json_encode([
            'success' => true,
            'result'=>["godkey"=> $this->godkey],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}




function apiMain($db,$request): IResponseBuilder
{
    //ここに処理を書く
    $version = $request['version'] ?? null;
    $signature = $request['signature'] ?? null;
    if (!$version || !$signature) {
        throw new ErrorResponseBuilder("Invalid input parameters.");
    }
    //versionチェック
    if($version!="urn::jsonversion"){
        throw new ErrorResponseBuilder("Invalid version");
    }
    /*
    :
    :
    */



    return new SuccessResponseBuilder($pubkey);

}





// SQLiteデータベースに接続
$db = Config::getRootDb();//new PDO('sqlite:benchmark_data.db');
$db->exec('BEGIN IMMEDIATE');
try{
    // POSTリクエストならJSONを受け取る
    $rawData = file_get_contents('php://input');
    // $rawData = file_get_contents('./upload_test.json');
    apiMain($db,$request)->sendResponse();
    }
    $db->exec("COMMIT");
}catch(ErrorResponseBuilder $e){
    $db->exec("ROLLBACK");
    $e->sendResponse();
}catch (Exception $e) {
    $db->exec("ROLLBACK");
    (new ErrorResponseBuilder($e->getMessage()))->sendResponse();
}
