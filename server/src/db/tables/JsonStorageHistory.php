<?php
namespace Jsonpost\db\tables;


use \PDO as PDO;
use \Exception as Exception;
use \Jsonpost\utils\UuidWrapper;


class JsonStorageHistoryRecord{
    public int $history_id;
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
            history_id INTEGER PRIMARY KEY,
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
    public function insert(int $history_id, int $id_json_storage):JsonStorageHistoryRecord
    {

        // SQLクエリを準備して実行
        $sql = "
        INSERT INTO $this->name (history_id, uuid, id_json_storage)
        VALUES (:history_id, :uuid, :id_json_storage);
        ";
        $uuid = UuidWrapper::create7();
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':history_id', $history_id, PDO::PARAM_INT);
        $stmt->bindParam(':uuid', $uuid->asBytes(), PDO::PARAM_LOB);
        $stmt->bindParam(':id_json_storage', $id_json_storage, PDO::PARAM_INT);
        $stmt->execute();
        // 挿入したレコードのIDを取得
        $insertedId = $this->db->lastInsertId();

        // 新しく挿入したレコードを取得（IDを使って取得）
        $sql = "SELECT * FROM $this->name WHERE history_id = ? LIMIT 1;";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$insertedId]);

        $value=$stmt->fetchObject('Jsonpost\db\tables\JsonStorageHistoryRecord');        
        if ($value === false) {
            throw new Exception('Insert failed.');
        }
        // 文字列をjson_decodeして返す
        return $value;
        

    }
}