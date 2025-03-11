<?php
namespace Jsonpost\endpoint;


use Jsonpost\db\tables\nst2024\{PropertiesTable,DbSpecTable};
use Jsonpost\db\tables\{EcdasSignedAccountRoot,JsonStorageHistory};


use Jsonpost\responsebuilder\ErrorResponseBuilder;


use Exception;
use PDO;

/**
 * 既に存在するアカウントにバインドしたRawAccount。
 * このエンドポイントは未初期化サーバーが初期化リクエストを受け入れるために存在します。
 */
class ZeroNonceEndpoint extends AccountBondEndpoint
{
    public static function create($db,?string $server_name,?string $rawData):ZeroNonceEndpoint{
        $stamp=parent::createStamp($server_name,$rawData);
        $ar_tbl=new EcdasSignedAccountRoot($db);
        $ar_ret=$ar_tbl->selectOrInsertIfNotExist($stamp->getEcdsaPubkey());
        if(!$ar_ret->is_new_record){
            ErrorResponseBuilder::throwResponse(501,hint:[]);
        }
        if($stamp->getNonceAsU48()!=0){
            ErrorResponseBuilder::throwResponse(204,hint:[]);
        } 
        return new ZeroNonceEndpoint(
                parent::getMsNow(),
                $stamp,
                $db,
                new PropertiesTable($db),
                $ar_ret,0xffffffff
        );
    }
}