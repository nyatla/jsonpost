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


// class PowDifficulityCalculator {
//     /**
//      * Powの動的難易度計算器
//      * 一定時間に受理する nonce の数を一定に保つように調整しながら、
//      * 入力された nonce が閾値を超えたか確認する。
//      */
//     private int $threshold;
//     private int $lastTimeLac = 0; // 最終計算時刻（ミリ秒）
//     private float $acceptPerSec = Config::NEW_ACCOUNT_PER_SEC; // 受理する nonce の目標レート（1秒あたり）

//     public function __construct(int $startThreshold,int $lastTimeLac) {
//         $this->threshold = $startThreshold;
//         $this->lastTimeLac = $lastTimeLac;
//     }

//     /**
//      * 現在の難易度を取得（32bit空間における log2 スケール）
//      */
//     public function getDifficulty(): float {
//         return 32 - log($this->threshold, 2);
//     }

//     /**
//      * nonce32 をチェックし、状態を更新する。
//      * nonce32 が閾値以下であれば true を返す。
//      */
//     public function update(?int $nonce32): bool {
//         $now = round(microtime(true) * 1000); // 現在の時刻（ミリ秒）
//         $ep = $now - $this->lastTimeLac; // 経過時間（ミリ秒）
//         $th = $this->threshold;
//         $ret = false;

//         if ($nonce32 === null) {
//             if ($ep > (1000 * 3 / $this->acceptPerSec)) {
//                 $th *= 2;
//             } elseif ($ep < (1000 / 3 / $this->acceptPerSec)) {
//                 // 60%を切っているなら閾値が高すぎるので下げる
//                 $th /= 2;
//             }
//         } elseif ($nonce32 <= $th) {
//             $this->lastTimeLac = $now;
//             $ret = true;

//             if ($ep > (1000 * 3 / $this->acceptPerSec)) {
//                 $th *= 2;
//             } elseif ($ep < (1000 * 2 / 3 / $this->acceptPerSec)) {
//                 // 60%を切っているなら閾値が高すぎるので下げる
//                 $th /= 2;
//             } else {
//                 // 適正範囲内だったら微調整
//                 $th *= $ep / (1000 * $this->acceptPerSec);
//             }
//         }

//         $this->threshold = round($th + 1);
//         return $ret;
//     }
// // }

// class SystemInfo
// {
//     public readonly Array $properties_records;
//     public function __construct($db) {
//         $pt=new PropertiesTable($db);
//         $this->properties_records=$pt->selectAllAsAssoc();
//     }
//     /**
//      * nonceを使って現在のルートdifficulityを更新する
//      */
//     public function update($nonce32):array
//     {      
//         $pbc=new PowDifficulityCalculator(
//             $this->properties_records[PropertiesTable::VNAME_ROOT_POW_DIFF_TH],
//             $this->properties_records[PropertiesTable::VNAME_ROOT_POW_ACCEPT_TIME]);
//         $ret=$pbc->update($nonce32);
//         return [$ret,$pbc->getDifficulty()];
//     }
// }


// * 攻撃対策:極端に低いdifficultyが単発で入力された場合どうするか？
// * →経過時間に上限を設けてリセットする。
// * →事前計算対策で抑制する

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
    // if($ar_ret->isInserted){
    //     //新規レコードの場合
    //     // $nonce32 = unpack('N', substr($ps->getHash(), 0, 4))[1];
    //     // [$isaccept,$difficulity]=$sysinfo->update($nonce32);
    //     // if(!$isaccept){
    //     //     throw new ErrorResponseBuilder( 'Pow不足');
    //     // }
    // }
    #初めての場合はnonce=0
    $ar_rec=$ar_ret->record;
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



