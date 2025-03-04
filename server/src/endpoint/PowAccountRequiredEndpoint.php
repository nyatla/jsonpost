<?php
namespace Jsonpost\endpoint;

use Jsonpost\db\tables\EcdasSignedAccountRootRecord;
use Jsonpost\db\tables\JsonStorage;
use Jsonpost\db\tables\nst2024\{PropertiesTable,DbSpecTable};
use Jsonpost\db\tables\{EcdasSignedAccountRoot,JsonStorageHistory,HistoryRecord};

use Jsonpost\utils\ecdsasigner\PowStamp;
use Jsonpost\responsebuilder\ErrorResponseBuilder;
use Jsonpost\utils\pow\{TimeSizeDifficultyBuilder};

use Exception;
use PDO;


/**
 * Historyと付帯レコードに関する操作バッチ
 */
class HistoryBatch{
    private $db;
    private JsonStorage $json_storage;
    public function __construct($db){
        $this->db = $db;
    }
    /**
     * history_.account_id==account_idのレコードの中でjson_storage_history.history_idにhistory_idがあるものの、history_idが最も大きいhistoryレコードを得る。
     * storage操作を行った最終レコードを得るために使う
     */
    public function selectLatestStorageHistoryByAccount($account_id):HistoryRecord|false
    {
        $sql="
            WITH filtered_history AS (
                SELECT h.*
                FROM history h
                INNER JOIN json_storage_history jsh
                    ON h.id = jsh.history_id
            )
            SELECT *
            FROM filtered_history
            WHERE id_account = :account_id
            ORDER BY id DESC
            LIMIT 1;
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':account_id', $account_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchObject('Jsonpost\db\tables\HistoryRecord');

        //存在しない場合はnull
    }
}






/**
 * PoWによる認証が行われたアカウント。
 * 
 */
class PoWAccountRequiredEndpoint extends StampRequiredEndpoint
{
    private readonly PDO $db;
    private readonly PropertiesTable $pt;

    public readonly EcdasSignedAccountRootRecord $account;
    public readonly int $required_pow;

    private function __construct(int $accepted_time,PowStamp $stamp,PDO $db, PropertiesTable $pt,EcdasSignedAccountRootRecord $account, int $required_pow){
        parent::__construct($accepted_time,$stamp);
        $this->db = $db;
        $this->pt = $pt;
        $this->account=$account;
        $this->required_pow=$required_pow;
    }

    public static function create(PDO $db,string $rawData):PoWAccountRequiredEndpoint
    {   
        // $this->db=$db;
        $pt=new PropertiesTable($db);
        // parent::__construct($pt->selectByName(PropertiesTable::VNAME_SERVER_NAME), $rawData);

        //nonceの確認
        $server_name=$pt->selectByName(PropertiesTable::VNAME_SERVER_NAME);
        $accepted_time=parent::getMsNow();
        $stamp=parent::createStamp($server_name, $rawData);
        $ar_tbl=new EcdasSignedAccountRoot($db);

        $ar_ret=$ar_tbl->selectOrInsertIfNotExist($stamp->getEcdsaPubkey());

        //nonce順位の確認。初めての場合はnonce=0スタート
        if($ar_ret->nonce>=$stamp->getNonceAsInt()){
            ErrorResponseBuilder::throwResponse(204,'Nonce must be greater than or equal to the current value.',hint:['current'=>$ar_ret->nonce]);
        }
        #powFieldの確認
        $pow32 = $stamp->getPowScore32();

        $json_size=strlen($rawData);
        $tsdb=TimeSizeDifficultyBuilder::fromText($pt->selectByName(PropertiesTable::VNAME_POW_ALGORITHM));
        $rate=1;
        if($ar_ret->is_new_record){
            //新規に作成されたアカウント
            $last_time=intval($pt->selectByName(PropertiesTable::VNAME_ROOT_POW_ACCEPT_TIME));
            $ep=$accepted_time-$last_time;
            //ms->sec,byte->kb換算する
            $rate=$tsdb->rate($ep/1000,$json_size/1000);
        }else{
            //既存アカウント
            $hb=new HistoryBatch($db);
            $latest_rec=$hb->selectLatestStorageHistoryByAccount($ar_ret->id);
            $last_time=$latest_rec?$latest_rec->timestapm:0;//0、ありえないのでは？DB削除しない限り
            $ep=$accepted_time-$last_time;
            //ms->sec,byte->kb換算する
            $rate=$tsdb->rate($ep/1000,$json_size/1000);
        }
        $required_pow=(int)(min(0xffffffff,pow(2,32*$rate)));
        if($pow32>$required_pow){
            ErrorResponseBuilder::throwResponse(205,"Pow score is high. Received:$pow32",hint:['required_score'=>$required_pow]);
        }
        return new PoWAccountRequiredEndpoint($accepted_time,$stamp,$db,$pt,$ar_ret,$required_pow);
    }
    /**
     * データベースのPOW-TIMEに打刻する。
     * @return void
     */
    public function commitStamp(){
        if($this->account->is_new_record){
            //新規レコードの場合はRootの受理時刻も更新
            //アカウントごとの情報は先に投入した初期値がある。
            $this->pt->upsert(PropertiesTable::VNAME_ROOT_POW_ACCEPT_TIME,$this->accepted_time);
        }else{
            //既存レコードの場合はnonceを更新しておく
            $et=new EcdasSignedAccountRoot($this->db);
            $et->updateRecord($this->account->id,$this->stamp->getNonceAsInt());
        }
    }


    
}