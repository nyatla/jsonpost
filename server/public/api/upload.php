<?php



/**
 * 所定の書式に格納したJSONファイルのアップロードを受け付けます。
 * 
 */
require dirname(__FILE__) .'/../../vendor/autoload.php'; // Composerでインストールしたライブラリを読み込む


// use JsonPost;
use Jsonpost\Config;
use Jsonpost\responsebuilder\{IResponseBuilder,ErrorResponseBuilder};
use Jsonpost\utils\ecdsasigner\{PowStamp};
use Jsonpost\utils\{UuidWrapper};

use Jsonpost\db\tables\nst2024\{PropertiesTable,DbSpecTable};
use Jsonpost\db\tables\{JsonStorageHistory,JsonStorage,EcdasSignedAccountRoot};
use Jsonpost\db\views\{JsonStorageView};



class SuccessResponseBuilder implements IResponseBuilder {
    private string $usr_uuid;
    private string $doc_uuid;
    private int $powbits;
    public function __construct(string $usr_uuid,string $doc_uuid,int $powbits) {
        $this->doc_uuid=$doc_uuid;
        $this->usr_uuid=$usr_uuid;
        $this->powbits=$powbits;
    }

    public function sendResponse() {
        header('Content-Type: application/json');
        http_response_code(200);
        
        echo json_encode([
            'success' => true,
            'result'=>[
                'status'=>'created',
                'user_uuid'=>$this->usr_uuid,
                'json_uuid'=>$this->doc_uuid,
                'score'=>[
                    'powbits'=>$this->powbits
                ]
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}

// アップロードAPIの処理
function apiMain($db,$rawData):IResponseBuilder
{
    $powstamp1 = $_SERVER['HTTP_POWSTAMP_1'] ?? null;


    $t1=new PropertiesTable($db);
    $server_name=$t1->selectByName(PropertiesTable::VNAME_SERVER_NAME);
    
    $ps=null;
    try{
        $ps=new PowStamp(hex2bin($powstamp1));
        if(!PowStamp::verify($ps,$server_name,$rawData,0)){
            throw new Exception('PowStamp verify failed');
        }
    } catch (Exception $e) {
        throw new ErrorResponseBuilder( $e->getMessage());
    } 

    //ここから書込み系の
    $ar_tbl=new EcdasSignedAccountRoot($db);

    $ar_rec=$ar_tbl->selectOrInsertIfNotExist($ps->getEcdsaPubkey());

    #初めての場合はnonce=0
    $nonce=$ps->getNonceAsInt();
    if($ar_rec['nonce']>=$nonce){
        throw new ErrorResponseBuilder("Invalid nonce. Current nonce={$ar_rec['nonce']}");
    }
    $current_powbits=$ps->getPowNonceAsInt();
    if($ar_rec['pow_bits_write']>$current_powbits){
        throw new ErrorResponseBuilder("Low powbits. Over {$ar_rec['pow_bits_write']} required.");
    }
    #文章を登録
    $js_tbl=new JsonStorage($db);
    $request = json_decode($rawData, true);
    if ($request === null) {
        throw new ErrorResponseBuilder("Invalid JSON format.");
    }    
    $js_rec=$js_tbl->selectOrInsertIfNotExist(json_encode($request,JSON_UNESCAPED_UNICODE));
    $uh_tbl=new JsonStorageHistory($db);
    $uh_tbl->insert($ar_rec['id'],$js_rec['id'],0,$current_powbits);
    #nonce更新
    $ar_tbl->updateNonce($ar_rec['id'],$nonce);
    $u1=UuidWrapper::loadFromBytes($ar_rec['uuid']);
    $u2=UuidWrapper::loadFromBytes($js_rec['uuid']);
    return new SuccessResponseBuilder($u1->asText(),$u2->asText(),$current_powbits);
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

    // アップロードAPI処理を呼び出す
    apiMain($db, $rawData)->sendResponse();
    $db->exec("COMMIT");
}catch(ErrorResponseBuilder $exception){
    $db->exec("ROLLBACK");
    $exception->sendResponse();
}catch(Exception $e){
    $db->exec("ROLLBACK");
    (new ErrorResponseBuilder($e->getMessage()))->sendResponse();
    // (new ErrorResponseBuilder("Internal Error"))->sendResponse();
}catch(Error $e){
    (new ErrorResponseBuilder($e->getMessage()))->sendResponse();
    // (new ErrorResponseBuilder('Internal Error.'))->sendResponse();
}



