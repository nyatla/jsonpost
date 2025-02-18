<?php
// namespace Jsonpost{
//     use \PDO as PDO;
//     use \Exception as Exception;

//     require_once dirname(__FILE__).'/nyatla_std_tables.php';
//     require_once dirname(__FILE__).'/../utils.php';


//     class EcdasSignedAccountRoot
//     {
//         public const VERSION='EcdasSignedAccountRoot:1';
//         private $db;
//         public readonly string $name;

//         public function __construct($db,$name="account_root")
//         {
//             $this->db = $db;
//             $this->name=$name;
//         }

//         // テーブル作成
//         public function createTable()
//         {
//             $sql = "
//             CREATE TABLE IF NOT EXISTS $this->name (
//                 id INTEGER PRIMARY KEY AUTOINCREMENT,
//                 pubkey BLOB NOT NULL UNIQUE,   --[RO] ecdasのrecoverkey[0]
//                 uuid BLOB NOT NULL UNIQUE,     --[RO] ユーザー識別のためのuuid
//                 nonce INTEGER NOT NULL,     --[RW] 署名データの下位8バイト(nonce)
//                 pow_diff INTEGER            --[RW] 現在のpowbits
//             );
//             ";
//             $this->db->exec($sql);
//         }
//         // pubkeysの何れかに一致するpubkeyを持つレコードを返す
//         public function selectOrInsertIfNotExist(string $pubkey)
//         {
//             // pubkey に一致するレコードを検索
//             $sql = "SELECT * FROM $this->name WHERE pubkey = ? LIMIT 1;";
//             $stmt = $this->db->prepare($sql);
//             $stmt->execute([$pubkey]);
//             $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
//             if ($record) {
//                 // レコードが存在する場合、そのレコードを返す
//                 return $record;
//             } else {
//                 // レコードが存在しない場合、新しいレコードを挿入
//                 $uuid = UuidWrapper::create7();
//                 $nonce = 0; // nonce 初期値
            
//                 // 新しいレコードを挿入
//                 $this->insert($pubkey, $uuid->asBytes(), $nonce);
            
//                 // 挿入したレコードのIDを取得
//                 $insertedId = $this->db->lastInsertId();

//                 // 新しく挿入したレコードを取得（IDを使って取得）
//                 $sql = "SELECT * FROM $this->name WHERE id = ? LIMIT 1;";
//                 $stmt = $this->db->prepare($sql);
//                 $stmt->execute([$insertedId]);
                
//                 // 新しく挿入したレコードを返す
//                 return $stmt->fetch(PDO::FETCH_ASSOC);
//             }
//         }
//         // データ挿入
//         public function insert(string $pubkey,string  $uuid,int $nonce)
//         {
//             $sql = "
//             INSERT INTO $this->name (pubkey, uuid, nonce) VALUES (?, ?, ?);
//             ";
//             $stmt = $this->db->prepare($sql);
//             $stmt->execute([$pubkey, $uuid, $nonce]);
//         }
//         public function updateNonce($id, $newNonce)
//         {
//             // 新しいnonceを指定したpubkeyに対して更新する
//             $sql = "UPDATE $this->name SET nonce = ? WHERE id = ?;";
//             $stmt = $this->db->prepare($sql);
//             $stmt->execute([$newNonce, $id]);
//         }

//         public function getNonceById($id)
//         {
//             // id で検索して nonce を取得
//             $sql = "SELECT nonce FROM $this->name WHERE id = ? LIMIT 1;";
//             $stmt = $this->db->prepare($sql);
//             $stmt->execute([$id]);
//             $nonce = $stmt->fetchColumn();
        
//             // nonce が見つからない場合
//             if ($nonce === false) {
//                 throw new Exception("指定された id のレコードが見つかりません。");
//             }
        
//             // nonce を返す
//             return $nonce;
//         }

//         // uuidによるアカウントの検索
//         public function getAccountByUuid($uuid)
//         {
//             $sql = "
//             SELECT * FROM $this->name WHERE uuid = ?;
//             ";
//             $stmt = $this->db->prepare($sql);
//             $stmt->execute([$uuid]);
//             return $stmt->fetch(PDO::FETCH_ASSOC);
//         }
//     }
//     // class JsonHolder

//     class JsonStorage
//     {
//         public const VERSION='JsonStorage:1';

//         private $db;
//         public readonly string $name;

//         public function __construct($db,$name="json_storage")
//         {
//             $this->db = $db;
//             $this->name=$name;
//         }


//         // テーブル作成
//         public function createTable()
//         {
//             $sql = "
//             CREATE TABLE IF NOT EXISTS $this->name (
//                 id INTEGER PRIMARY KEY AUTOINCREMENT,
//                 uuid BLOB NOT NULL UNIQUE,         -- [RO]システム内の文章識別ID
//                 hash BLOB NOT NULL UNIQUE,         -- [RO]識別子/文章ハッシュ jsonの内容から作成したsha256
//                 json JSON NOT NULL                 -- [RO]実際のJSONデータ（そのまま保存）
//             )";
//             $this->db->exec($sql);
//         }

//         // jsonDataのSHA-256ハッシュを取り、そのハッシュを使ってUUID5を生成（URL名前空間使用）
//         public function selectOrInsertIfNotExist(string $jsonData)
//         {
//             // jsonDataのSHA-256ハッシュを計算
//             $hash = hex2bin(hash('sha256', $jsonData)); // JSONデータのSHA-256ハッシュ

//             // UUID v5を生成（URLの名前空間UUIDとjsonDataのSHA-256ハッシュを基に）
//             $uuid = UuidWrapper::create7(); // 名前空間をURLとしてUUID v5を生成

//             // まず既存のレコードを検索
//             $sql = "SELECT * FROM $this->name WHERE hash = :hash";
//             $stmt = $this->db->prepare($sql);
//             $stmt->bindParam(':hash', $hash);
//             $stmt->execute();
//             $record = $stmt->fetch();

//             if ($record) {
//                 // レコードが存在すれば返す
//                 return $record;
//             } else {
//                 // レコードが存在しなければ挿入
//                 $sqlInsert = "
//                 INSERT INTO $this->name (uuid, hash, json)
//                 VALUES (:uuid, :hash, json(:json))
//                 ";
//                 $stmtInsert = $this->db->prepare($sqlInsert);
//                 $uuid_v=$uuid->asBytes();
//                 $stmtInsert->bindParam(':uuid', $uuid_v);
//                 $stmtInsert->bindParam(':hash', $hash);
//                 $stmtInsert->bindParam(':json', $jsonData);
//                 $stmtInsert->execute();

//                 // 挿入したレコードのIDを取得
//                 $insertedId = $this->db->lastInsertId();

//                 // 新しく挿入したレコードを取得（IDを使って取得）
//                 $sql = "SELECT * FROM $this->name WHERE id = ? LIMIT 1;";
//                 $stmt = $this->db->prepare($sql);
//                 $stmt->execute([$insertedId]);
                
//                 // 新しく挿入したレコードを返す
//                 return $stmt->fetch(PDO::FETCH_ASSOC);
//             }
//         }
//         public function selectByUuid(string $uuid): array
//         {
//             // 最初に、uuidが一致するレコードのindexを取得する
//             $indexSql = "
//             SELECT *
//             FROM $this->name
//             WHERE hex(uuid) == :uuid;
//             ";
//             // echo(strtoupper(bin2hex($uuid)));        
//             // $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
//             $stmt = $this->db->prepare($indexSql);
//             $t=strtoupper(bin2hex($uuid));
//             $stmt->bindValue(':uuid', $t, PDO::PARAM_STR);
//             // $stmt->debugDumpParams();
//             $stmt->execute();

//             // 結果を取得
//             $result = $stmt->fetch(PDO::FETCH_ASSOC);

//             // レコードが存在しない場合は例外をスロー
//             if (!$result) {
//                 throw new Exception("レコードが見つかりませんでした。UUID: $t");
//             }
//             return $result;

            
        
//             // // 結果を返す
//             // return [
//             //     'items' => $items,
//             //     'total' => (int)$total,  // レコードの総数
//             // ];
//        }


//     }
    
//     class JsonStorageHistory
//     {
//         public const VERSION='JsonStorageHistory:1';    
//         private $db;
//         public readonly string $name;

//         public function __construct($db,$name="json_storage_history")
//         {
//             $this->name= $name;
//             $this->db = $db;
//         }
//     // テーブルを作成する
//         public function createTable()
//         {
//             $sql = "
//             CREATE TABLE IF NOT EXISTS $this->name (
//                 id INTEGER PRIMARY KEY AUTOINCREMENT,
//                 created_date INTEGER NOT NULL,     -- [RO]データの投入時刻（UNIXタイムスタンプを想定）
//                 id_account INTEGER NOT NULL,       -- [RO]文章を所有するアカウントID
//                 id_json_storage INTEGER NOT NULL,  -- [RO]文章のID
//                 opcode INTEGER NOT NULL
//             );
//             ";

//             $this->db->exec($sql);
//         }
//         // データをjson_storageテーブルに挿入
//         public function insert(int $idAccount, int $idJsonStorage,int $opCode=0)
//         {
//             // 現在のUnixタイムスタンプを取得
//             $createdDate = time();

//             // SQLクエリを準備して実行
//             $sql = "
//             INSERT INTO $this->name (created_date, id_account, id_json_storage,opcode)
//             VALUES (:created_date, :id_account, :id_json_storage,:opcode);
//             ";

//             $stmt = $this->db->prepare($sql);
//             $stmt->bindParam(':created_date', $createdDate);
//             $stmt->bindParam(':id_account', $idAccount);
//             $stmt->bindParam(':id_json_storage', $idJsonStorage);
//             $stmt->bindParam(':opcode', $opCode);
//             $stmt->execute();
//         }
//     }
// }
// ?>