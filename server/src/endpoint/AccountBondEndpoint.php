<?php
namespace Jsonpost\endpoint;

use Jsonpost\db\tables\EcdasSignedAccountRootRecord;
use Jsonpost\db\tables\nst2024\{PropertiesTable,DbSpecTable};


use Jsonpost\utils\ecdsasigner\PowStamp2;


use PDO;

/**
 * アカウントがバインドされたエンドポイント。
 */
abstract class AccountBondEndpoint extends StampRequiredEndpoint
{
    private readonly PDO $db;
    private readonly PropertiesTable $pt;
    public readonly EcdasSignedAccountRootRecord $account;
    public readonly int $required_pow;
    protected function __construct(int $accepted_time,PowStamp2 $stamp,PDO $db, PropertiesTable $pt,EcdasSignedAccountRootRecord $account, int $required_pow){
        parent::__construct($accepted_time,$stamp);
        $this->db = $db;
        $this->pt = $pt;
        $this->account=$account;
        $this->required_pow=$required_pow;
    }
}