<?php
/* Nyatla.jp標準規格テーブル
*/
namespace Jsonpost\db\tables\nst2024;

use PDO;
use Exception;
use \Jsonpost\responsebuilder\ErrorResponseBuilder;

class PropertiesRows {
    public string $version;
    public string $god;
    public string $pow_algorithm;
    public ?string $server_name;
    public int $root_pow_accept_time;

    public function __construct(array $data) {
        $this->version = $data[PropertiesTable::VNAME_VERSION];
        $this->god = $data[PropertiesTable::VNAME_GOD];
        $this->pow_algorithm = $data[PropertiesTable::VNAME_POW_ALGORITHM];
        $this->server_name = $data[PropertiesTable::VNAME_SERVER_NAME];
        $this->root_pow_accept_time =  (int)$data[PropertiesTable::VNAME_ROOT_POW_ACCEPT_TIME];
    }
}

class PropertiesTable
{
    public const TABLE_NAME='properties';
    public const VNAME_POW_ALGORITHM='pow_algorithm';
    public const VNAME_SERVER_NAME='server_name';
    public const VNAME_ROOT_POW_ACCEPT_TIME='root.pow_accept_time';
    public const VNAME_VERSION='version';
    public const VNAME_GOD='god';
    
    private $db;
    private $name;

    public function __construct($db,$name=PropertiesTable::TABLE_NAME)
    {
        $this->db = $db;
        $this->name= $name;
    }

    public function createTable()
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS {$this->name} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            value TEXT
        );
        ";
        $this->db->exec($sql);
    }

    public function insert($name, $value)
    {
        $sql = "INSERT INTO {$this->name} (name, value) VALUES (:name, :value)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':value', $value, PDO::PARAM_STR);
        $stmt->execute();
    }
    public function selectByName(string $name): ?string
    {
        // 最初に、uuidが一致するレコードのindexを取得する
        $indexSql = "
        SELECT value
        FROM $this->name
        WHERE name == :name;
        ";
        $stmt = $this->db->prepare($indexSql);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->execute();

        // 結果を取得
        $result = $stmt->fetch(PDO::FETCH_NUM);

        // レコードが存在しない場合は例外をスロー
        if (!$result) {
            ErrorResponseBuilder::throwResponse(401);
        }
        return $result[0];
    }
    public function selectAll(int $mode=PDO::FETCH_ASSOC)
    {
        $indexSql = "
        SELECT name,value
        FROM $this->name";
        $stmt = $this->db->prepare($indexSql);
        $stmt->execute();

        // 結果を取得
        $result = $stmt->fetchAll($mode);

        return $result;
    }
    public function selectAllAsObject():PropertiesRows{
        return new PropertiesRows(($this->selectAll()));
    }

    
    public function updateParam(string $name, string $value)
    {
        // SQLクエリを準備
        $sql = "UPDATE {$this->name} SET value = :value WHERE name = :name";
        
        // プリペアドステートメントの準備
        $stmt = $this->db->prepare($sql);
        
        // バインドする値をセット
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':value', $value, PDO::PARAM_STR);
        
        // クエリを実行
        $stmt->execute();        
    }
}