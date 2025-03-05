<?php
namespace Jsonpost\endpoint;

use Jsonpost\db\tables\EcdasSignedAccountRootRecord;
use Jsonpost\db\tables\nst2024\{PropertiesTable,DbSpecTable};
use Jsonpost\db\tables\{EcdasSignedAccountRoot,JsonStorageHistory};

use Jsonpost\utils\ecdsasigner\PowStamp;
use Jsonpost\responsebuilder\ErrorResponseBuilder;
use Jsonpost\utils\pow\{TimeSizeDifficultyBuilder};

use Exception;
use PDO;

/**
 * 既に存在するアカウントにバインドしたアカウント。
 * Powは不要。
 * 新規アカウント生成はしません。
 */
class AccountEndpoint extends AccountBondEndpoint
{
    private readonly PDO $db;
    private readonly PropertiesTable $pt;

    public readonly EcdasSignedAccountRootRecord $account;
    public readonly int $required_pow;


    
    public static function create(PDO $db,?string $rawData):AccountEndpoint
    {   
        // $this->db=$db;
        $pt=new PropertiesTable($db);
        // parent::__construct($pt->selectByName(PropertiesTable::VNAME_SERVER_NAME), $rawData);

        //nonceの確認
        $server_name=$pt->selectByName(PropertiesTable::VNAME_SERVER_NAME);
        $accepted_time=parent::getMsNow();
        $stamp=parent::createStamp($server_name, $rawData);
        $ar_tbl=new EcdasSignedAccountRoot($db);

        $ar_ret=$ar_tbl->select($stamp->getEcdsaPubkey());
        if($ar_ret===false){
            ErrorResponseBuilder::throwResponse(401);
        }
        return new AccountEndpoint($accepted_time,$stamp,$db,$pt,$ar_ret,0xffffffff);
    }
}
