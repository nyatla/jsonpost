<?php
namespace Jsonpost\utils;

use Jsonpost\db\tables\nst2024\{PropertiesTable,DbSpecTable};
use Jsonpost\db\tables\{EcdasSignedAccountRoot,JsonStorageHistory};
use Jsonpost\utils\pow\TimeSizeDifficultyBuilder;
use Jsonpost\utils\ecdsasigner\PowStamp;
use Jsonpost\responsebuilder\ErrorResponseBuilder;

use Exception;
/**
 * データベースロックは考慮してないから書込みAPI使うときはちゃんとトランザクションはって
 */
class ApplicationInstance
{

    






    private \PDO $db;
    private PropertiesTable $pt;
    public readonly Array $properties_records;
    public function __construct($db) {
        $pt=new PropertiesTable($db);
        $this->properties_records=$pt->selectAll();
        $this->pt=$pt;
        $this->db=$db;
    }
    /**
     * ミリ秒単位の現在時刻
     * @return int
     */
    public static function getMsNow():int{
        return round(microtime(true) * 1000); // 現在の時刻（ミリ秒）
    }
    /**
     * PoWのAccept敷居値。この敷居値よりも小さい場合にそのPowは有効。
     * min(0xffffffff,2^(32*rate)
     * Summary of getRootDifficulty
     * @param mixed $now
     * @param mixed $json_size
     * @return void
     */
    public function getRootDifficulty($now,$json_size):float{
        $tsdb=TimeSizeDifficultyBuilder::fromText($this->properties_records[PropertiesTable::VNAME_POW_ALGORITHM]);
        $last_time=intval($this->properties_records[PropertiesTable::VNAME_ROOT_POW_ACCEPT_TIME]);
        $ep=$now-$last_time;
        //ms->sec,byte->kb換算する
        $rate=$tsdb->rate($ep/1000,$json_size/1000);
        return min(0xffffffff,pow(2,32*$rate));
    }
    /**
     * ユーザー単位で最終成功時刻からPow閾値を計算
     * @param mixed $user_id
     * @param mixed $now
     * @param mixed $json_size
     * @return float|int|object
     */
    public function getUserDifficulty($user_id,$now,$json_size):float{        
        $tsdb=TimeSizeDifficultyBuilder::fromText($this->properties_records[PropertiesTable::VNAME_POW_ALGORITHM]);
        $history_tbl=new JsonStorageHistory($this->db);
        $latest_rec=$history_tbl->selectLatestByAccount($user_id);
        $last_time=$latest_rec?$latest_rec->created_date:0;//0、ありえないのでは？
        $ep=$now-$last_time;
        //ms->sec,byte->kb換算する
        $rate=$tsdb->rate($ep/1000,$json_size/1000);
        return min(0xffffffff,pow(2,32*$rate));
    }
    /**
     * pow32が難易度を満たしているか確認する。失敗した場合例外を発生する。
     * @param mixed $now
     * @param mixed $json_size
     * @param mixed $pow32
     * @return void
     */
    public function ckeckRootDifficulity($now,$json_size,$pow32){
        $target=$this->getRootDifficulty($now,$json_size);
        if($pow32<=$target){
            return;
        }
        throw new Exception("Pow score is low.");
    }
    public function ckeckUserDifficulity($user_id,$now,$json_size,$pow32){
        $target=$this->getUserDifficulty($user_id,$now,$json_size);
        if($pow32<=$target){
            return;
        }
        throw new Exception("Pow score is low.");
    }

    /**
     * データベースに値を書き込む
     * @param mixed $name
     * @param mixed $v
     * @return void
     */
    public function updateProperty($name,$v){
        $this->pt->updateParam($name,$v);
    }
    public function updateLRPT(int $now){
        //root_powの最終時刻を更新
        $this->pt->updateParam(PropertiesTable::VNAME_ROOT_POW_ACCEPT_TIME,$now);
    }

    /**
     * nonceを使って現在のルートdifficulityを更新する。データベースも更新する
     */
    // public function update($nonce32):array
    // {      
    //     $pbc=new PowDifficulityCalculator(
    //         intval($this->properties_records[PropertiesTable::VNAME_ROOT_POW_DIFF_TH]),
    //         intval($this->properties_records[PropertiesTable::VNAME_ROOT_POW_ACCEPT_TIME]));
    //     $ret=$pbc->update($nonce32);
    //     $this->pt->updatePowParams($pbc->getThreshold(),$pbc->getLastTimeLac());

    //     return [$ret,$pbc->getDifficulty()];
    // }
}
