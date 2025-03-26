<?php
namespace Jsonpost\endpoint;


use Jsonpost\db\tables\EcdasSignedAccountRootRecord;
use Jsonpost\db\tables\nst2024\{PropertiesTable,DbSpecTable};
use Jsonpost\db\tables\{EcdasSignedAccountRoot,JsonStorageHistory,HistoryRecord};

use Jsonpost\responsebuilder\ErrorResponseBuilder;
use Jsonpost\utils\ecdsasigner\PowStamp2;
use Jsonpost\utils\ecdsasigner\PowStamp2Message;




/**
 * GodOperationのエンドポイント。
 * このエンドポイントはGodアカウントが操作を行うため、Nonceを消費しませんが、ハッシュチェーンは進行します。
 * 
 */
class GodEndpoint extends AccountBondEndpoint
{
    public static function create($db,?string $rawData):GodEndpoint{
        $pt=new PropertiesTable($db);
        $god_pubkey=hex2bin($pt->selectByName(PropertiesTable::VNAME_GOD));
        //Godと一致する？
        $stamp=PowStamp2::createFromHeader();
        if($god_pubkey!=$stamp->getEcdsaPubkey()){
            ErrorResponseBuilder::throwResponse(206,);
        }
        //Godのアカウントを検索
        $ar_rec=EcdasSignedAccountRootRecord::selectAccountByPubkey($db,$god_pubkey);
        if($ar_rec===false){
            ErrorResponseBuilder::throwResponse(401);
        }
        //Godアカウントの最終PowStamp履歴を得る
        $god_rec=HistoryRecord::selectLatestHistoryByAccount($db,$ar_rec->id);
        if($god_rec===false){
            ErrorResponseBuilder::throwResponse(401);//神がいない
        }

        $god_stamp=$god_rec->powstampMessageAsObject();
        //Stampのベリファイ
        // print_r(bin2hex($god_stamp->getHash()));
        $psm=$stamp->recoverMessage($god_stamp->getHash(), $rawData);
        if(!$stamp->verify($psm)){
            ErrorResponseBuilder::throwResponse(203);//ベリファイ失敗
        }
        
        //Nonceは更新されていない？
        if($stamp->getNonceAsU48()!=$god_stamp->getNonceAsU48()){
            ErrorResponseBuilder::throwResponse(204,hint:["current"=>$god_stamp->getNonceAsU48()]);
        }
        return new GodEndpoint(
            $stamp,$psm,
                $db,
                $pt,
                $ar_rec,self::UINT48_MAX
        );
    }
}