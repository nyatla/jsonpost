<?php
namespace Jsonpost\db\tables;



class JsonStorageHistory
{
    public const VERSION='JsonStorageHistory:1';    
    private $db;
    public readonly string $name;

    public function __construct($db,$name="json_storage_history")
    {
        $this->name= $name;
        $this->db = $db;
    }
// テーブルを作成する
    public function createTable()
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS $this->name (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_date INTEGER NOT NULL,     -- [RO]データの投入時刻（UNIXタイムスタンプを想定）
            id_account INTEGER NOT NULL,       -- [RO]文章を所有するアカウントID
            id_json_storage INTEGER NOT NULL,  -- [RO]文章のID
            opcode INTEGER NOT NULL
        );
        ";

        $this->db->exec($sql);
    }
    // データをjson_storageテーブルに挿入
    public function insert(int $idAccount, int $idJsonStorage,int $opCode=0)
    {
        // 現在のUnixタイムスタンプを取得
        $createdDate = time();

        // SQLクエリを準備して実行
        $sql = "
        INSERT INTO $this->name (created_date, id_account, id_json_storage,opcode)
        VALUES (:created_date, :id_account, :id_json_storage,:opcode);
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':created_date', $createdDate);
        $stmt->bindParam(':id_account', $idAccount);
        $stmt->bindParam(':id_json_storage', $idJsonStorage);
        $stmt->bindParam(':opcode', $opCode);
        $stmt->execute();
    }
}