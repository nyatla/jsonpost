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
 * GodOperationのエンドポイント。
 * このエンドポイントはGodアカウントが操作を行うためのNonceを消費しないEndpointです。
 */
class GodEndpoint extends AccountBondEndpoint
{
    public static function create($db,?string $rawData):GodEndpoint{
        $pt=new PropertiesTable($db);
        $server_name=$pt->selectByName(PropertiesTable::VNAME_SERVER_NAME);
        $stamp=parent::createStamp($server_name,$rawData);
        $ar_tbl=new EcdasSignedAccountRoot($db);
        $ar_ret=$ar_tbl->select($stamp->getEcdsaPubkey());
        if($ar_ret===false){
            ErrorResponseBuilder::throwResponse(401,hint:[]);
        }
        $pt=new PropertiesTable($db);
        if($pt->selectByName(PropertiesTable::VNAME_GOD)!=$ar_ret->pubkeyAsHex()){
            ErrorResponseBuilder::throwResponse(206,hint:[]);
        }

        return new GodEndpoint(
                parent::getMsNow(),
                $stamp,
                $db,
                $pt,
                $ar_ret,0xffffffff
        );
    }
}