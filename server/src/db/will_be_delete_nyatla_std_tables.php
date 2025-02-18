<?php
// /* Nyatla.jp標準規格テーブル
// */
// namespace Jsonpost{

//     class PropertiesTable
//     {
//         private $db;

//         public function __construct($db)
//         {
//             $this->db = $db;
//         }

//         public function createTable()
//         {
//             $sql = "
//             CREATE TABLE IF NOT EXISTS properties (
//                 id INTEGER PRIMARY KEY AUTOINCREMENT,
//                 name TEXT NOT NULL,
//                 value TEXT NOT NULL
//             );
//             ";
//             $this->db->exec($sql);
//         }

//         public function insert($name, $value)
//         {
//             $sql = "INSERT INTO properties (name, value) VALUES (:name, :value)";
//             $stmt = $this->db->prepare($sql);
//             $stmt->bindValue(':name', $name, SQLITE3_TEXT);
//             $stmt->bindValue(':value', $value, SQLITE3_TEXT);
//             $stmt->execute();
//         }


//     }

//     class DbSpecTable
//     {
//         private $db;

//         public function __construct($db)
//         {
//             $this->db = $db;
//         }

//         public function createTable()
//         {
//             $sql = "
//             CREATE TABLE IF NOT EXISTS dbspec (
//                 id INTEGER PRIMARY KEY AUTOINCREMENT,
//                 version TEXT NOT NULL,
//                 tablename TEXT NOT NULL,
//                 params TEXT
//             );
//             ";
//             $this->db->exec($sql);
//         }

//         public function insert($version, $tablename, $params = '')
//         {
//             $sql = "INSERT INTO dbspec (version, tablename, params) VALUES (:version, :tablename, :params)";
//             $stmt = $this->db->prepare($sql);
//             $stmt->bindValue(':version', $version, SQLITE3_TEXT);
//             $stmt->bindValue(':tablename', $tablename, SQLITE3_TEXT);
//             $stmt->bindValue(':params', $params, SQLITE3_TEXT);
//             $stmt->execute();
//         }        
//     }
// }
// /////////////////////////////////


// ?>