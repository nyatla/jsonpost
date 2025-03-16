<?php
namespace Jsonpost\db\tables;



use \PDO as PDO;
use \Jsonpost\utils\UuidWrapper;





class EcdasSignedAccountRoot
{
    public const VERSION='EcdasSignedAccountRoot:2';
    private $db;
    public readonly string $name;

    public function __construct(PDO $db, string $name = "account_root")
    {
        $this->db = $db;
        $this->name=$name;
    }

    // テーブル作成
    public function createTable():EcdasSignedAccountRoot
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS $this->name (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            pubkey BLOB NOT NULL UNIQUE,   --[RO] ecdasのrecoverkey[0]
            uuid BLOB NOT NULL UNIQUE     --[RO] ユーザー識別のためのuuid
        );
        ";
        $this->db->exec($sql);
        return $this;
    }
    


    

    // データ挿入
    public function insert(string $pubkey,string  $uuid)
    {
        $sql = "
        INSERT INTO $this->name (pubkey, uuid) VALUES (:pubkey, :uuid);";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':pubkey', $pubkey, PDO::PARAM_LOB);
        $stmt->bindParam(':uuid', $uuid, PDO::PARAM_LOB);
        $stmt->execute();
        
    }
    // public function updateRecord($id, int $nonce)
    // {
    //     // id をキーにして nonce と powtime を同時に更新する
    //     $sql = "UPDATE $this->name SET nonce = ? WHERE id = ?;";
    //     $stmt = $this->db->prepare($sql);
    //     $stmt->execute([$nonce, $id]);
    // }


    

    public function selectOrInsertIfNotExist(string $pubkey):EcdasSignedAccountRootRecord
    {
        $r=EcdasSignedAccountRootRecord::select($this->db,$pubkey);
        if($r!==false){
            return $r;
        }
        // レコードが存在しない場合、新しいレコードを挿入            
        $uuid = UuidWrapper::create7();
    
        // 新しいレコードを挿入
        $this->insert($pubkey, $uuid->asBytes());
    
        // 挿入したレコードのIDを取得
        $insertedId = $this->db->lastInsertId();

        // 新しく挿入したレコードを取得（IDを使って取得）
        $sql = "SELECT * FROM $this->name WHERE id = ? LIMIT 1;";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$insertedId]);
        
        // 新しく挿入したレコードを返す
        return $stmt->fetchObject('Jsonpost\db\tables\EcdasSignedAccountRootRecord',[true]);
    }
    
    // uuidによるアカウントの検索

    
    
}