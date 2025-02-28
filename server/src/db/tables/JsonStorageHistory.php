<?php
namespace Jsonpost\db\tables;


use \PDO as PDO;


class JsonStorageHistoryRow{
    public ?int $id;
    public int $created_date;
    public int $id_account;
    public int $id_json_storage;
    public int $opcode;
    public int $pownonce;
    public int $pownonce_required;
    // カラム名とプロパティ名が一致している必要があります
    // 名前が異なる場合は、SQL側でエイリアスを使用するか、__set()マジックメソッドで対応します
}

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
            opcode INTEGER NOT NULL,   -- [RO]操作コード(0)
            pownonce INTEGER NOT NULL, --[RO]登録時に使用したPowNonce
            pownonce_required INTEGER NOT NULL --[RO]登録時に必要だったPowNonce
        );
        ";

        $this->db->exec($sql);
    }
    /**
     * アカウントIDについて、最終更新レコードを得る。
     * @param int $id_account
     * @return null or
     */
    public function selectLatestByAccount(int $id_account):JsonStorageHistoryRow
    {
        // SQLクエリ
        $sql = "
            SELECT * FROM {$this->name} 
            WHERE id_account = :id_account 
            ORDER BY created_date DESC 
            LIMIT 1
        ";
        // クエリの実行
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_account', $id_account, PDO::PARAM_INT);
        $stmt->execute();
        return  $stmt->fetchObject('Jsonpost\db\tables\JsonStorageHistoryRow');
    }

    /**
     * 新しいレコードを追加
     * @param int $idAccount
     * @param int $idJsonStorage
     * @param int $opCode
     * @param int $pownonce
     * @return void
     */
    public function insert(int $createdDate,int $idAccount, int $idJsonStorage,int $opCode,int $pownonce,int $pownonce_required)
    {

        // SQLクエリを準備して実行
        $sql = "
        INSERT INTO $this->name (created_date, id_account, id_json_storage,opcode,pownonce,pownonce_required)
        VALUES (:created_date, :id_account, :id_json_storage,:opcode,:pownonce,:pownonce_required);
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':created_date', $createdDate);
        $stmt->bindParam(':id_account', $idAccount);
        $stmt->bindParam(':id_json_storage', $idJsonStorage);
        $stmt->bindParam(':opcode', $opCode);
        $stmt->bindParam(':pownonce', $pownonce);
        $stmt->bindParam(':pownonce_required', $pownonce_required);

        $stmt->execute();
    }
}