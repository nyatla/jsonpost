<?php
/* Nyatla.jp標準規格テーブル
*/
namespace Jsonpost\db\tables\nst2024;

use PDO;
class DbSpecTable
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function createTable():DbSpecTable
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS dbspec (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            version TEXT NOT NULL,
            tablename TEXT NOT NULL,
            params TEXT
        );
        ";
        $this->db->exec($sql);
        return $this;
    }

    public function insert($version, $tablename, $params = '')
    {
        $sql = "INSERT INTO dbspec (version, tablename, params) VALUES (:version, :tablename, :params)";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':version', $version, PDO::PARAM_STR);
        $stmt->bindValue(':tablename', $tablename, PDO::PARAM_STR);
        $stmt->bindValue(':params', $params, PDO::PARAM_STR);
        $stmt->execute();
    }        
}
