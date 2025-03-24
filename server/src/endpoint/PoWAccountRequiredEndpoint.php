<?php
namespace Jsonpost\endpoint;

use Jsonpost\db\tables\nst2024\{PropertiesTable,PropertiesRows};
use Jsonpost\db\tables\{EcdasSignedAccountRoot,EcdasSignedAccountRootRecord,HistoryRecord};


use Jsonpost\utils\ecdsasigner\PowStamp2;
use Jsonpost\responsebuilder\ErrorResponseBuilder;


use PDO;







/**
 * PoWによる認証を行うエンドポイント。
 * Powstampの署名、nonce,hashを確認します。
 * 
 * 
 */
class PoWAccountRequiredEndpoint extends AStampRequiredEndpoint
{
    private readonly PDO $db;
    public readonly EcdasSignedAccountRootRecord $account;
    public readonly int $required_pow;
    public readonly int $accepted_pow;
    public readonly PropertiesRows $properties;
    // public readonly int $next_nonce;

    
    private function __construct(PowStamp2 $stamp,int $accepted_time,PDO $db, PropertiesRows $ptr,EcdasSignedAccountRootRecord $account, int $required_pow, int $accepted_pow){
        parent::__construct($stamp,$accepted_time);
        $this->db = $db;
        $this->account=$account;
        $this->required_pow=$required_pow;
        $this->accepted_pow=$accepted_pow;
        $this->properties=$ptr;
        #次回のnonceの計算
        // $cost=pow(2,48-log($required_pow,2));
        // $this->next_nonce=min(self::UINT48_MAX,round($account->nonce+$cost+.5));

    }
    
    


    public static function create(PDO $db,string $rawData):PoWAccountRequiredEndpoint
    {   
        //stamp生成
        $stamp=PowStamp2::createFromHeader();

        //verify
        $pt=new PropertiesTable($db);
        $pt_rec=$pt->selectAllAsObject();        

        $ar_tbl=new EcdasSignedAccountRoot($db);

        
        $latest_hist_rec=null;
        $pubkey=$stamp->getEcdsaPubkey();
        $ar_ret=EcdasSignedAccountRootRecord::select($db,$pubkey);
        if($ar_ret===false){
            if(!$pt_rec->welcome){
                ErrorResponseBuilder::throwResponse(207);//not wellcomeで見つからない→新規
            }
            $ar_ret=$ar_tbl->selectOrInsertIfNotExist($pubkey);

            $latest_hist_rec=HistoryRecord::selectLatestAccountFirstHistory($db);
        }else{
            $latest_hist_rec=HistoryRecord::selectLatestHistoryByAccount($db,$ar_ret->id);
        }
        if($latest_hist_rec===false){
            ErrorResponseBuilder::throwResponse(502,message:'This pass is not considerd.',status:405);
        }
        $latest_stamp=$latest_hist_rec->powstampAsObject();
        //Nonceの確認
        if($latest_stamp->getNonceAsU48()>=$stamp->getNonceAsU48()){
            ErrorResponseBuilder::throwResponse(204,'Nonce must be greater than to the current value.',hint:['current'=>$latest_stamp->getNonceAsU48()]);
        }

        //powスコアの確認
        $last_time=$latest_hist_rec->timestamp;
        $json_size=strlen($rawData);
        $accepted_time=parent::getMsNow();
        $ep=$accepted_time-$last_time;
        $rate=$pt_rec->pow_algorithm->rate($ep/1000,$json_size/1000);        //ms->sec,byte->kb換算する
        
        $required_pow=(int)(min(self::UINT48_MAX,pow(2,48*$rate)));
        // print_r(bin2hex($latest_stamp->getHash()).",\n");
        // print_r(bin2hex($stamp->getHash()).",\n");
        $powscore48=$stamp->recoverMessage($latest_stamp->getHash(), $rawData)->getPowScoreU48();
        if($powscore48>=$required_pow){ #PowSdoreは既定よりも大きくなければならない。
            ErrorResponseBuilder::throwResponse(205,"Pow score is high. Received:$powscore48",hint:['required_score'=>$required_pow]);
        }

        return new PoWAccountRequiredEndpoint($stamp,$accepted_time,$db,$pt_rec,$ar_ret,$required_pow,$powscore48);        
    }
}