<?php
namespace Jsonpost\endpoint;

use Jsonpost\db\tables\EcdasSignedAccountRootRecord;
use Jsonpost\db\tables\nst2024\{PropertiesTable,DbSpecTable};
use Jsonpost\db\tables\{EcdasSignedAccountRoot,JsonStorageHistory};

use Jsonpost\utils\ecdsasigner\PowStamp;
use Jsonpost\responsebuilder\ErrorResponseBuilder;
use Jsonpost\utils\pow\{TimeSizeDifficultyBuilder};

use Exception;
use PDO;

/**
 * 初期化済みのStapmインスタンス。
 * commitを実行することでインスタンス情報をデータベースに保存する。
 * 
 */
class StampRequiredEndpoint extends RawStampRequiredEndpoint
{
    private readonly PDO $db;
    private readonly PropertiesTable $pt;
    // public readonly Array $properties_records;
    /**
     * HttpヘッダからPowStampV1ヘッダを読み出す。成功しない場合は適切な例外を搬出します。
     * @throws \Jsonpost\responsebuilder\ErrorResponseBuilder
     * @return PowStamp|null
     */
    public readonly EcdasSignedAccountRootRecord $account;
    public readonly int $required_pow;
    /**
     * 受け付けた時間[ms]
     * @var int
     */
    public readonly int $accepted_time;

    public function __construct(PDO $db,string $rawData)
    {   
        $this->db=$db;
        $pt=new PropertiesTable($db);
        parent::__construct($pt->selectByName(PropertiesTable::VNAME_SERVER_NAME), $rawData);

        //nonceの確認
        $stamp=$this->stamp;
        $ar_tbl=new EcdasSignedAccountRoot($db);

        $ar_ret=$ar_tbl->selectOrInsertIfNotExist($stamp->getEcdsaPubkey());

        //nonce順位の確認。初めての場合はnonce=0スタート
        if($ar_ret->nonce>=$stamp->getNonceAsInt()){
            ErrorResponseBuilder::throwResponse(204,'Nonce must be greater than or equal to the current value.',hint:['current'=>$ar_ret->nonce]);
        }
        #powFieldの確認
        $pow32 = $stamp->getPowScore32();

        $json_size=strlen($rawData);
        $now=self::getMsNow();
        $tsdb=TimeSizeDifficultyBuilder::fromText($pt->selectByName(PropertiesTable::VNAME_POW_ALGORITHM));

        $rate=1;
        if($ar_ret->is_new_record){
            $last_time=intval($pt->selectByName(PropertiesTable::VNAME_ROOT_POW_ACCEPT_TIME));
            $ep=$now-$last_time;
            //ms->sec,byte->kb換算する
            $rate=$tsdb->rate($ep/1000,$json_size/1000);
        }else{
            $history_tbl=new JsonStorageHistory($this->db);
            $latest_rec=$history_tbl->selectLatestByAccount($ar_ret->id);
            $last_time=$latest_rec?$latest_rec->created_date:0;//0、ありえないのでは？DB削除しない限り
            $ep=$now-$last_time;
            //ms->sec,byte->kb換算する
            $rate=$tsdb->rate($ep/1000,$json_size/1000);
        }
        $required_pow=(int)(min(0xffffffff,pow(2,32*$rate)));
        if($pow32>$required_pow){
            ErrorResponseBuilder::throwResponse(205,"Pow score is high. Received:$pow32",hint:['required_score'=>$required_pow]);
        }
        $this->required_pow=$required_pow;
        $this->account=$ar_ret;
        // $this->properties_records=$pt->selectAll();
        $this->pt=$pt;
        $this->accepted_time=$now;
    }
    /**
     * データベースのPOW-TIMEに打刻する。
     * @return void
     */
    public function commitStamp(){
        //新規の場合
        if($this->account->is_new_record){
            $this->pt->updateParam(PropertiesTable::VNAME_ROOT_POW_ACCEPT_TIME,$this->accepted_time);
        }else{
            $et=new EcdasSignedAccountRoot($this->db);
            $et->updateRecord($this->account->id,$this->stamp->getNonceAsInt());
        }
    }

    private static function getMsNow():int{
        return round(microtime(true) * 1000); // 現在の時刻（ミリ秒）
    }
}