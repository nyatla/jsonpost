<?php
namespace Jsonpost\db\tables;

use Jsonpost\db\tables\nst2024\PropertiesTable;
use \PDO as PDO;
use \Exception as Exception;
use \Jsonpost\utils\UuidWrapper;



class EcdasSignedAccountRootRecord {

    public ?int $id = null;    
    public string $pubkey;
    public string $uuid;
    public int $nonce;

    public readonly int $is_new_record;
    public function __construct($is_new_record){
        $this->is_new_record = $is_new_record;
    }
}

class EcdasSignedAccountRoot
{
    public const VERSION='EcdasSignedAccountRoot:1';
    private $db;
    public readonly string $name;

    public function __construct($db,$name="account_root")
    {
        $this->db = $db;
        $this->name=$name;
    }

    // テーブル作成
    public function createTable()
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS $this->name (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pubkey BLOB NOT NULL UNIQUE,   --[RO] ecdasのrecoverkey[0]
            uuid BLOB NOT NULL UNIQUE,     --[RO] ユーザー識別のためのuuid
            nonce INTEGER NOT NULL         --[RW] 署名データの下位8バイト(nonce)
        );
        ";
        $this->db->exec($sql);
    }
    


    /**
     * pubkeysの何れかに一致するpubkeyを持つレコードを返す
     * @return array(bool,mixed) 新規フラグ,レコード
     */
    public function selectOrInsertIfNotExist(string $pubkey):EcdasSignedAccountRootRecord
    {
        // pubkey に一致するレコードを検索
        $sql = "SELECT * FROM $this->name WHERE pubkey = ? LIMIT 1;";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$pubkey]);
        $record = $stmt->fetchObject('Jsonpost\db\tables\EcdasSignedAccountRootRecord',[false]);
        if ($record) {
            // レコードが存在する場合、そのレコードを返す
            return $record;
        } else {
            // レコードが存在しない場合、新しいレコードを挿入            
            $uuid = UuidWrapper::create7();
            $nonce = 0; // nonce 初期値
        
            // 新しいレコードを挿入
            $this->insert($pubkey, $uuid->asBytes(), $nonce);
        
            // 挿入したレコードのIDを取得
            $insertedId = $this->db->lastInsertId();

            // 新しく挿入したレコードを取得（IDを使って取得）
            $sql = "SELECT * FROM $this->name WHERE id = ? LIMIT 1;";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$insertedId]);
            
            // 新しく挿入したレコードを返す
            return $stmt->fetchObject('Jsonpost\db\tables\EcdasSignedAccountRootRecord',[true]);
        }
    }
    // データ挿入
    public function insert(string $pubkey,string  $uuid,int $nonce)
    {
        $sql = "
        INSERT INTO $this->name (pubkey, uuid, nonce)
        SELECT ?, ?, ?";
        // $sql = "
        // INSERT INTO $this->name (pubkey, uuid, nonce) VALUES (?, ?, ?);
        // ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$pubkey, $uuid, $nonce]);
    }
    public function updateNonce($id, $newNonce)
    {
        // 新しいnonceを指定したpubkeyに対して更新する
        $sql = "UPDATE $this->name SET nonce = ? WHERE id = ?;";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$newNonce, $id]);
    }

    public function getNonceById($id): mixed
    {
        // id で検索して nonce を取得
        $sql = "SELECT nonce FROM $this->name WHERE id = ? LIMIT 1;";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $nonce = $stmt->fetchColumn();
    
        // nonce が見つからない場合
        if ($nonce === false) {
            throw new Exception("指定された id のレコードが見つかりません。");
        }
    
        // nonce を返す
        return $nonce;
    }

    // uuidによるアカウントの検索
    public function getAccountByUuid($uuid):EcdasSignedAccountRootRecord
    {
        $sql = "
        SELECT * FROM $this->name WHERE uuid = ?;
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$uuid]);
        return $stmt->fetchObject('EcdasSignedAccountRootRecord',[false]);
    }
}