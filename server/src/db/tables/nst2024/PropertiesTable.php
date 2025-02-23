<?php
/* Nyatla.jp標準規格テーブル
*/
namespace Jsonpost\db\tables\nst2024;

use PDO;
use \Exception as Exception;

class PropertiesTable
{
    public const TABLE_NAME='properties';
    public const VNAME_DEFAULT_POWBITS_W='default.pow_bits_write';
    public const VNAME_DEFAULT_POWBITS_R='default.pow_bits_read';
    public const VNAME_SERVER_NAME='server_name';
    public const VNAME_VERSION='version';
    
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
            throw new Exception("No data. name:{$name}");
        }
        return $result[0];
    }
    public function selectAll()
    {
        $indexSql = "
        SELECT name,value
        FROM $this->name";
        $stmt = $this->db->prepare($indexSql);
        $stmt->execute();

        // 結果を取得
        $result = $stmt->fetchAll(PDO::FETCH_NUM);

        return $result;
    }    
}