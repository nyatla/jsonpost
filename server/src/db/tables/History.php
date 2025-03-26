<?php
namespace Jsonpost\db\tables;


use \PDO as PDO;
use \Exception as Exception;



class History
{
    public const VERSION='History:1';
    private $db;
    public readonly string $name;

    public function __construct($db,$name="history")
    {
        $this->name= $name;
        $this->db = $db;
    }
// テーブルを作成する
    public function createTable():History
    {
        $sql = "
        CREATE TABLE $this->name (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            timestamp INTEGER NOT NULL,   -- [RO]データの投入時刻(UNIXタイムスタンプを想定
            id_account INTEGER NOT NULL,  -- [RO]操作を行ったアカウント
            powstampmsg BLOB NOT NULL,    -- [RO]登録時に使用したPowStampMessage
            pow_required INTEGER NOT NULL -- [RO]要求されていたPowScore
        );";

        $this->db->exec($sql);
        return $this;
    }

    

    /**
     * 
     * @param int $timestamp
     * @param int $id_account
     * @param int $powstampmsg
     * @param int $pow_algolithm
     */
    public function insert(int $timestamp, int $id_account,string $powstampmsg,int $pow_required):HistoryRecord
    {

        // SQLクエリを準備して実行
        $sql = "
        INSERT INTO $this->name (timestamp, id_account, powstampmsg,pow_required)
        VALUES (:timestamp, :id_account, :powstampmsg,:pow_required);
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':timestamp', $timestamp, PDO::PARAM_INT);
        $stmt->bindParam(':id_account', $id_account, PDO::PARAM_INT);
        $stmt->bindParam(':powstampmsg', $powstampmsg, PDO::PARAM_LOB);
        $stmt->bindParam(':pow_required', $pow_required, PDO::PARAM_INT);
        $stmt->execute();
        // 挿入したレコードのIDを取得
        $insertedId = $this->db->lastInsertId();

        // 新しく挿入したレコードを取得（IDを使って取得）
        $sql = "SELECT * FROM $this->name WHERE id = ? LIMIT 1;";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$insertedId]);
        
        // 新しく挿入したレコードを返す
        $value=$stmt->fetchObject('Jsonpost\db\tables\HistoryRecord');
        if ($value===false){
            throw new Exception('Insert failed.');
        }
        return $value;
    }
}
