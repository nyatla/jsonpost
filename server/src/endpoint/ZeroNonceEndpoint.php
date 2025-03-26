<?php
namespace Jsonpost\endpoint;


use Jsonpost\db\tables\nst2024\{PropertiesTable,DbSpecTable};
use Jsonpost\db\tables\{EcdasSignedAccountRoot,JsonStorageHistory};
use Jsonpost\utils\ecdsasigner\PowStamp2;


use Jsonpost\responsebuilder\ErrorResponseBuilder;
use Jsonpost\utils\ecdsasigner\PowStamp2Message;



/**
 * Nonce=0のみ受け付けるEndpoint
 * このエンドポイントは未初期化サーバーが初期化リクエストを受け入れるために存在します。
 */
class ZeroNonceEndpoint extends AccountBondEndpoint
{
    public static function create($db,string $genesis_hash,string $rawData):ZeroNonceEndpoint{
        $stamp=PowStamp2::createFromHeader();
        $stamp_message=$stamp->recoverMessage($genesis_hash,$rawData);
        if(!$stamp->verify($stamp_message)){
            ErrorResponseBuilder::throwResponse(203);
        }
        //データベースが存在しないときだけ生成できる
        $ar_tbl=new EcdasSignedAccountRoot($db);
        $ar_ret=$ar_tbl->selectOrInsertIfNotExist($stamp->getEcdsaPubkey());
        if(!$ar_ret->is_new_record){
            ErrorResponseBuilder::throwResponse(501);
        }
        if($stamp->getNonceAsU48()!=0){
            ErrorResponseBuilder::throwResponse(204,hint:[]);
        } 
        return new ZeroNonceEndpoint(
            $stamp,
            $stamp_message,
            $db,
                new PropertiesTable($db),
                $ar_ret,0xffffffff
        );
    }
}