<?php
/* Nyatla.jp標準規格テーブル
*/
namespace Jsonpost\db\tables\nst2024;

use PDO;
class PropertiesTable
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function createTable()
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS properties (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            value TEXT NOT NULL
        );
        ";
        $this->db->exec($sql);
    }

    public function insert($name, $value)
    {
        $sql = "INSERT INTO properties (name, value) VALUES (:name, :value)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':name', $name, PDO::PARAM_STR);
        $stmt->bindValue(':value', $value, PDO::PARAM_STR);
        $stmt->execute();
    }
}