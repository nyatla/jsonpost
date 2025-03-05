<?php
namespace Jsonpost\db\tables;


use \PDO as PDO;
use \Exception as Exception;
use \Jsonpost\utils\UuidWrapper;


class JsonStorageHistoryRecord{
    public int $id_history;
    public string $uuid;
    public int $id_json_storage;
    // カラム名とプロパティ名が一致している必要があります
    // 名前が異なる場合は、SQL側でエイリアスを使用するか、__set()マジックメソッドで対応します
    public function uuidAsText(): string{
        return UuidWrapper::loadFromBytes($this->uuid);
    }
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
    public function createTable():JsonStorageHistory
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS $this->name (
            id_history INTEGER PRIMARY KEY,
            uuid BLOB NOT NULL,    -- [RO]システム内の文章識別ID
            id_json_storage INTEGER NOT NULL  -- [RO]文章のID
            );";

        $this->db->exec($sql);
        return $this;
    }

    /**
     * 新しいレコードを追加
     * @param int $idAccount
     * @param int $idJsonStorage
     * @param int $opCode
     * @param int $pownonce
     * @return void
     */
    public function insert(int $id_history, int $id_json_storage):JsonStorageHistoryRecord
    {

        // SQLクエリを準備して実行
        $sql = "
        INSERT INTO $this->name (id_history, uuid, id_json_storage)
        VALUES (:id_history, :uuid, :id_json_storage);
        ";
        $uuid = UuidWrapper::create7();
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id_history', $id_history, PDO::PARAM_INT);
        $stmt->bindParam(':uuid', $uuid->asBytes(), PDO::PARAM_LOB);
        $stmt->bindParam(':id_json_storage', $id_json_storage, PDO::PARAM_INT);
        $stmt->execute();
        // 挿入したレコードのIDを取得
        $insertedId = $this->db->lastInsertId();

        // 新しく挿入したレコードを取得（IDを使って取得）
        $sql = "SELECT * FROM $this->name WHERE id_history = ? LIMIT 1;";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$insertedId]);

        $value=$stmt->fetchObject('Jsonpost\db\tables\JsonStorageHistoryRecord');        
        if ($value === false) {
            throw new Exception('Insert failed.');
        }
        // 文字列をjson_decodeして返す
        return $value;
    }
    public function getUuidOffset($uuid): int {
        // 最初に、UUIDの位置を取得
        $sql = "
        SELECT COUNT(*) 
        FROM $this->name 
        WHERE id_history < (
            SELECT id_history 
            FROM $this->name 
            WHERE uuid = :uuid
            LIMIT 1
        );";
    
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':uuid', $uuid, PDO::PARAM_LOB);
        $stmt->execute();
    
        // 結果を取得
        $position = $stmt->fetchColumn();
    
        if ($position === false) {
            throw new Exception("UUID not found.");
        }
    
        // オフセットを返す
        return (int) $position;
    }    
    /**
     * レコードの総数を返す。
     * @return int
     */
    public function totalCount(): int
    {
        // SQLクエリを準備
        $sql = "SELECT COUNT(*) FROM $this->name;";
        
        // クエリを実行
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        // 結果を取得し、返す
        return (int) $stmt->fetchColumn();
    }
}