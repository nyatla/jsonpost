<?php
/* Nyatla.jp標準規格テーブル
*/
namespace Jsonpost\db\tables\nst2024;

use Jsonpost\utils\pow\ITimeSizeDifficultyProvider;
use Jsonpost\utils\pow\TimeSizeDifficultyBuilder;
use PDO;
use Exception;
use \Jsonpost\responsebuilder\ErrorResponseBuilder;

class PropertiesRows {
    public string $version;
    public string $god;
    /**
     * jsonオブジェクト
     * @var object
     */
    public ITimeSizeDifficultyProvider $pow_algorithm;
    public ?string $server_name;
    public int $root_pow_accept_time;

    public function __construct(array $data) {
        $a=[];
        foreach($data as $k){
            $a[$k[0]]=$k[1];
        } 

        $this->version = $a[PropertiesTable::VNAME_VERSION];
        $this->god = $a[PropertiesTable::VNAME_GOD];
        $this->pow_algorithm =TimeSizeDifficultyBuilder::fromText($a[PropertiesTable::VNAME_POW_ALGORITHM]);
        $this->server_name = $a[PropertiesTable::VNAME_SERVER_NAME];
        $this->root_pow_accept_time =  (int)$a[PropertiesTable::VNAME_ROOT_POW_ACCEPT_TIME];
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

    public function createTable():PropertiesTable
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS {$this->name} (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT UNIQUE NOT NULL,
            value TEXT
        );
        ";
        $this->db->exec($sql);
        return $this;
    }
    public function upsert(string $name, ?string $value)
    {
        $sql = "
            INSERT OR REPLACE INTO {$this->name} (name, value)
            VALUES (:name, :value);
        ";
    
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':value', $value, PDO::PARAM_STR);
    
        if (!$stmt->execute()) {
            throw new Exception("Failed to upsert param '{$name}'");
        }
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
        return new PropertiesRows(($this->selectAll(PDO::FETCH_NUM)));
    }

    
    
    
}