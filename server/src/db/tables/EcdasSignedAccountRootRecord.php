<?php
namespace Jsonpost\db\tables;


use Jsonpost\responsebuilder\ErrorResponseBuilder;
use \PDO as PDO;
use \Jsonpost\utils\UuidWrapper;



class EcdasSignedAccountRootRecord {

    public ?int $id = null;    
    public string $pubkey;
    public string $uuid;

    
    public readonly int $is_new_record;
    public function __construct($is_new_record){
        $this->is_new_record = $is_new_record;
    }
    public function uuidAsText(): string{
        return UuidWrapper::loadFromBytes($this->uuid);
    }
    public function pubkeyAsHex(): string{
        return bin2hex($this->pubkey);
    }
    public static function select(PDO $db,string $pubkey):EcdasSignedAccountRootRecord|false
    {
        // pubkey に一致するレコードを検索
        $sql = "SELECT * FROM account_root WHERE (pubkey) = (:pubkey) LIMIT 1;";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':pubkey', $pubkey, PDO::PARAM_LOB);
        $stmt->execute();
        return $stmt->fetchObject('Jsonpost\db\tables\EcdasSignedAccountRootRecord',[false]);
    }

    
    // uuidによるアカウントの検索
    public static function selectAccountByUuid(PDO $db,$uuid):EcdasSignedAccountRootRecord|false
    {
        $sql = "
        SELECT * FROM account_root WHERE uuid = :uuid;
        ";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':uuid',$uuid, PDO::PARAM_LOB);
        $stmt->execute();
        $ret=$stmt->fetchObject('Jsonpost\db\tables\EcdasSignedAccountRootRecord',[false]);
        return $ret;        
    }
    public static function selectAccountByPubkey(PDO $db,string $pubkey):EcdasSignedAccountRootRecord|false
    {
        $sql = "SELECT * FROM account_root WHERE pubkey = :pubkey LIMIT 1;";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':pubkey',$pubkey, PDO::PARAM_LOB);
        $stmt->execute();
        $ret=$stmt->fetchObject('Jsonpost\db\tables\EcdasSignedAccountRootRecord',[false]);
        // nonce が見つからない場合
        return $ret;
    }
}