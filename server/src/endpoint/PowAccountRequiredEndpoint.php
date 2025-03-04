<?php
namespace Jsonpost\endpoint;

use Jsonpost\db\tables\nst2024\{PropertiesTable,DbSpecTable};
use Jsonpost\db\tables\{EcdasSignedAccountRoot,JsonStorageHistory,HistoryRecord,EcdasSignedAccountRootRecord};
use Jsonpost\db\batch\HistoryBatch;

use Jsonpost\utils\ecdsasigner\PowStamp;
use Jsonpost\responsebuilder\ErrorResponseBuilder;
use Jsonpost\utils\pow\{TimeSizeDifficultyBuilder};

use Exception;
use PDO;







/**
 * PoWによる認証を行うエンドポイント。
 * このアカウントは自動で新規に生成される。
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

    public static function create(PDO $db,string $rawData,bool $createifnotexist=false):PoWAccountRequiredEndpoint
    {   
        // $this->db=$db;
        $pt=new PropertiesTable($db);
        // parent::__construct($pt->selectByName(PropertiesTable::VNAME_SERVER_NAME), $rawData);

        //nonceの確認
        $server_name=$pt->selectByName(PropertiesTable::VNAME_SERVER_NAME);
        $accepted_time=parent::getMsNow();
        $stamp=parent::createStamp($server_name, $rawData);
        $ar_tbl=new EcdasSignedAccountRoot($db);

        $ar_ret=false;
        if($createifnotexist){
            $ar_ret=$ar_tbl->selectOrInsertIfNotExist($stamp->getEcdsaPubkey());
        }else{
            $ar_ret=$ar_tbl->select($stamp->getEcdsaPubkey());
            if($ar_ret===false){
                ErrorResponseBuilder::throwResponse(401);
            }
        }

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
            $last_time=$latest_rec?$latest_rec->timestamp:0;//0、ありえないのでは？DB削除しない限り
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