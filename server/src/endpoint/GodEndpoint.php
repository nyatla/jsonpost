<?php
namespace Jsonpost\endpoint;


use Jsonpost\db\tables\EcdasSignedAccountRootRecord;
use Jsonpost\db\tables\nst2024\{PropertiesTable,DbSpecTable};
use Jsonpost\db\tables\{EcdasSignedAccountRoot,JsonStorageHistory,HistoryRecord};

use Jsonpost\responsebuilder\ErrorResponseBuilder;
use Jsonpost\utils\ecdsasigner\PowStamp2;




/**
 * GodOperationのエンドポイント。
 * このエンドポイントはGodアカウントが操作を行うため、Nonceを消費しませんが、ハッシュチェーンは進行します。
 * 
 */
class GodEndpoint extends AccountBondEndpoint
{
    public static function create($db,?string $rawData):GodEndpoint{
        $pt=new PropertiesTable($db);
        //Godのhashを引く
        $god_pubkey=hex2bin($pt->selectByName(PropertiesTable::VNAME_GOD));
        $ar_rec=EcdasSignedAccountRootRecord::selectAccountByPubkey($db,$god_pubkey);
        if($ar_rec===false){
            ErrorResponseBuilder::throwResponse(206,);
        }
        $god_rec=HistoryRecord::selectLatestHistoryByAccount($db,$god_pubkey);
        if($god_rec===false){
            ErrorResponseBuilder::throwResponse(401);//神がいない
        }
        $god_stamp=$god_rec->powstampAsObject();
        //GodのLatestHashでStampを構成できる？
        $stamp=PowStamp2::createVerifiedFromHeader($god_stamp->getHash(),$rawData);
        //Nonceは更新されていない？
        if($stamp->getNonceAsU48()!=$god_stamp->getNonceAsU48()){
            ErrorResponseBuilder::throwResponse(204,hint:["current"=>$god_stamp->getNonceAsU48()]);
        }
        return new GodEndpoint(
            $stamp,
                $db,
                $pt,
                $ar_rec,self::UINT48_MAX
        );
    }
}