<?php
namespace Jsonpost\endpoint;

use Jsonpost\db\tables\EcdasSignedAccountRootRecord;
use Jsonpost\db\tables\nst2024\{PropertiesTable,DbSpecTable};


use Jsonpost\utils\ecdsasigner\PowStamp2;


use Jsonpost\utils\ecdsasigner\PowStamp2Message;
use PDO;

/**
 * アカウントがバインドされたエンドポイント。
 */
abstract class AccountBondEndpoint extends AStampRequiredEndpoint
{
    private readonly PDO $db;
    private readonly PropertiesTable $pt;
    public readonly EcdasSignedAccountRootRecord $account;
    public readonly int $required_pow;
    protected function __construct(PowStamp2 $stamp,PowStamp2Message $stamp_message,PDO $db, PropertiesTable $pt,EcdasSignedAccountRootRecord $account, int $required_pow){
        parent::__construct($stamp,$stamp_message);
        $this->db = $db;
        $this->pt = $pt;
        $this->account=$account;
        $this->required_pow=$required_pow;
    }
}