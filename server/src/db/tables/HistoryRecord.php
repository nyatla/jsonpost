<?php
namespace Jsonpost\db\tables;

use Jsonpost\utils\ecdsasigner\PowStamp2;
use \PDO as PDO;

class HistoryRecord{
    public int $id;
    public int $timestamp;
    public int $id_account;
    public string $powstamp;
    public int $pow_required;
    public function  powstampAsObject():PowStamp2{
        return new PowStamp2($this->powstamp);
    }

    public static function selectLatestStorageHistoryByAccount(PDO $db,$account_id):HistoryRecord|false
    {
        $sql="
            WITH filtered_history AS (
                SELECT h.*
                FROM history h
                INNER JOIN json_storage_history jsh
                    ON h.id = jsh.id_history
            )
            SELECT *
            FROM filtered_history
            WHERE id_account = :account_id
            ORDER BY id DESC
            LIMIT 1;
        ";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':account_id', $account_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchObject('Jsonpost\db\tables\HistoryRecord');

    }
    public static function selectLatestHistoryByAccount(PDO $db, $account_id):HistoryRecord|false
    {
        $sql="
            SELECT *
            FROM history
            WHERE id_account = :account_id
            ORDER BY id DESC
            LIMIT 1;
        ";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':account_id', $account_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchObject('Jsonpost\db\tables\HistoryRecord');
    }
    /**
     * 最も新しいACCOUNTの最も古いアカウントを得る
     * @param \PDO $db
     * @return bool|object
     */
    public static function selectLatestAccountFirstHistory(PDO $db):HistoryRecord|false
    {
        $sql="
            WITH first_account AS (
                SELECT id
                FROM account_root
                ORDER BY id DESC
                LIMIT 1
            )
            SELECT *
            FROM history
            WHERE id_account = (SELECT id FROM first_account)
            ORDER BY id ASC
            LIMIT 1;
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchObject('Jsonpost\db\tables\HistoryRecord');

    }


}
