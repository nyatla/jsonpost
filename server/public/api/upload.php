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
use Jsonpost\utils\{UuidWrapper,ApplicationInstance};

use Jsonpost\db\tables\nst2024\{PropertiesTable,DbSpecTable};
use Jsonpost\db\tables\{JsonStorageHistory,JsonStorage,EcdasSignedAccountRoot};




// * 攻撃対策:極端に低いdifficultyが単発で入力された場合どうするか？
// * →経過時間に上限を設けてリセットする。
// * →事前計算対策で抑制する

class SuccessResponseBuilder implements IResponseBuilder {
    private string $usr_uuid;
    private string $doc_uuid;
    private int $pownonce;
    public function __construct(string $usr_uuid,string $doc_uuid,int $pownonce) {
        $this->doc_uuid=$doc_uuid;
        $this->usr_uuid=$usr_uuid;
        $this->pownonce=$pownonce;
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
                    'pownonce'=>$this->pownonce
                ]
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}

// アップロードAPIの処理
function apiMain($db,$rawData):IResponseBuilder
{
    $powstamp1 = $_SERVER['HTTP_POWSTAMP_1'] ?? null;
    $sysinfo=new ApplicationInstance($db);

    $server_name=$sysinfo->properties_records[PropertiesTable::VNAME_SERVER_NAME];
    
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

    $ar_ret=$ar_tbl->selectOrInsertIfNotExist($ps->getEcdsaPubkey());

    //nonce順位の確認。初めての場合はnonce=0
    $nonce=$ps->getNonceAsInt();
    if($ar_ret->nonce>=$nonce){
        throw new ErrorResponseBuilder("Invalid nonce. Current nonce={$ar_ret->nonce}");
    }
    #powFieldの確認
    $nonce32 = unpack('N', substr($ps->getHash(), 0, 4))[1];

    if($ar_ret->is_new_record){
        $now=ApplicationInstance::getMsNow();
        $sysinfo->ckeckRootDifficulity($now,strlen($rawData),$nonce32);
        $sysinfo->updateLRPT($now);
    }else{
        //暫定でルートを使う
        $now=ApplicationInstance::getMsNow();
        $sysinfo->ckeckUserDifficulity($ar_ret->id, $now,strlen($rawData),$nonce32);
    }
    //Pow部分を取得
    $current_pownonce=$ps->getPowNonceAsInt();

    #文章を登録
    $js_tbl=new JsonStorage($db);
    $request = json_decode($rawData, true);
    if ($request === null) {
        throw new ErrorResponseBuilder("Invalid JSON format.");
    }    
    $js_rec=$js_tbl->selectOrInsertIfNotExist(json_encode($request,JSON_UNESCAPED_UNICODE));
    $uh_tbl=new JsonStorageHistory($db);
    $uh_tbl->insert($ar_ret->id,$js_rec->id,0,$current_pownonce);
    #nonce更新
    $ar_tbl->updateNonce($ar_ret->id,$nonce);
    $u1=UuidWrapper::loadFromBytes($ar_ret->uuid);
    $u2=UuidWrapper::loadFromBytes($js_rec->uuid);
    return new SuccessResponseBuilder($u1->asText(),$u2->asText(),$current_pownonce);
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



