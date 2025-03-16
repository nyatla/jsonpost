<?php
namespace Jsonpost\db\batch;

use Jsonpost\db\tables\JsonStorage;
use Jsonpost\db\tables\{EcdasSignedAccountRoot,JsonStorageHistory,HistoryRecord};

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
                    ON h.id = jsh.id_history
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

    }
    public function selectLatestHistoryByAccount($account_id):HistoryRecord|false
    {
        $sql="
            SELECT *
            FROM history
            WHERE id_account = :account_id
            ORDER BY id DESC
            LIMIT 1;
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':account_id', $account_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchObject('Jsonpost\db\tables\HistoryRecord');
    }
    public function selectLatestAccountFirstHistory():HistoryRecord
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
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':account_id', $account_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchObject('Jsonpost\db\tables\HistoryRecord');

    }


}
