<?php
namespace Jsonpost\db\tables;


use Exception;
use \PDO as PDO;



class OperationHistoryRecord{
    public int $id_history;
    public string $method;
    public string $operation; //JSON-string
    public function operationAsJson():mixed {
        return json_decode($this->operation);
    }

}

class OperationHistory
{
    public const VERSION='OperationHistory:1';
    public const METHOD_SET_GOD='set.god';
    public const METHOD_SET_POW_ALGORITHM='set.pow_algorithm';
    public const METHOD_SET_SERVER_NAME='set.server_name';
    public const METHOD_SET_WELCOME='set.welcome';
    private $db;
    public readonly string $name;

    public function __construct($db,$name="operation_history")
    {
        $this->name= $name;
        $this->db = $db;
    }
// テーブルを作成する
    public function createTable():OperationHistory
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS $this->name (
            id_history INTEGER PRIMARY KEY,
            method TEXT NOT NULL,
            operation JSON
        )";

        $this->db->exec($sql);
        return $this;
    }

    

    public function insert(int $id_history, string $method,mixed $operation): OperationHistoryRecord
    {

        // SQLクエリを準備して実行
        $sql = "
        INSERT INTO $this->name (id_history, method,operation)
        VALUES (:id_history,:method,json(:operation));";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id_history', $id_history, PDO::PARAM_INT);
        $stmt->bindParam(':method', $method, PDO::PARAM_STR);
        $stmt->bindParam(':operation', json_encode($operation), PDO::PARAM_STR);
        $stmt->execute();

        // 挿入したレコードのIDを取得
        $insertedId = $this->db->lastInsertId();

        // 新しく挿入したレコードを取得（IDを使って取得）
        $sql = "SELECT * FROM $this->name WHERE id_history = ? LIMIT 1;";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$insertedId]);
        
        $value=$stmt->fetchObject('Jsonpost\db\tables\OperationHistoryRecord');
        if ($value === false) {
            throw new Exception('Insert failed.');
        }
        // 文字列をjson_decodeして返す
        return $value;
    }
    public function getLatestByMethod(string $method):OperationHistoryRecord{
        $sql = "
        SELECT * 
        FROM $this->name
        WHERE method = :method
        ORDER BY id_history DESC
        LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':method', $method, PDO::PARAM_STR);
        $stmt->execute();

        $value = $stmt->fetchObject('Jsonpost\db\tables\OperationHistoryRecord');

        if ($value === false) {
            throw new Exception('Insert failed.');
        }
        // 文字列をjson_decodeして返す
        return $value;
    }    
}