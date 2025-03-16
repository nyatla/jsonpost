<?php






/**
 * POWSTAMPがある場合はaccountの情報も得られる。
 */




require dirname(__FILE__) .'/../../vendor/autoload.php'; // Composerでインストールしたライブラリを読み込む


use Jsonpost\Config;



use Jsonpost\endpoint\{VerifiedStampEndpoint};
use Jsonpost\responsebuilder\{ErrorResponseBuilder,SuccessResultResponseBuilder};
use Jsonpost\db\tables\nst2024\{PropertiesTable};
use Jsonpost\db\tables\{EcdasSignedAccountRootRecord,HistoryRecord};





$db = Config::getRootDb();//new PDO('sqlite:benchmark_data.db');
try{
    //前処理
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ErrorResponseBuilder::throwResponse(err_code: 101,status:405);
    }

    $chain_json=null;
    $account_json=null;
    if(isset($_SERVER['HTTP_POWSTAMP_2'])){
        //スタンプがついてたらaccountの情報も取る
        $endpoint=VerifiedStampEndpoint::create();
        $act_rec=EcdasSignedAccountRootRecord::selectAccountByPubkey($db,$endpoint->stamp->getEcdsaPubkey());
        if($act_rec!==false){
            $account_json=[
                'uuid'=>$act_rec->uuidAsText(),
            ];

            $hrec=HistoryRecord::selectLatestHistoryByAccount($db,$act_rec->id);
            if($hrec!==false){
                //PowStampのアカウントが存在し、かつフォークを済ませている。
                $pstamp=$hrec->powstampAsObject();
                $chain_json=[
                    'domain'=>'blanch',
                    'latest_hash'=>bin2hex($pstamp->getHash()), #ハッシュはblanchのものであるべき
                    'nonce'=>$pstamp->getNonceAsU48()
                ];
            }else{
                //フォークしてるけどHistoryにはない。
                ErrorResponseBuilder::throwResponse(101,message:'This pass is not considerd.',status:405);
            }
        }else{
            //新規アカウントっぽい。ハッシュは最後に登録されたアカウントの初めのHistoryを得る
            $hrec=HistoryRecord::selectLatestAccountFirstHistory($db);
            if($hrec===false){
                ErrorResponseBuilder::throwResponse(101,message:'This pass is not considerd.',status:405);
            }
            $pstamp=$hrec->powstampAsObject();
            $chain_json=[
                'domain'=>'main',
                'latest_hash'=>bin2hex($pstamp->getHash()), #ハッシュはblanchのものであるべき
                'nonce'=>$pstamp->getNonceAsU48()
        ];
        }
    }else{
            //新規アカウントっぽい。ハッシュは最後に登録されたアカウントの初めのHistoryを得る
            $hrec=HistoryRecord::selectLatestAccountFirstHistory($db);
            if($hrec===false){
                ErrorResponseBuilder::throwResponse(101,message:'This pass is not considerd.',status:405);
            }
            $pstamp=$hrec->powstampAsObject();
            $chain_json=[
                'domain'=>'main',
                'latest_hash'=>bin2hex($pstamp->getHash()), #ハッシュはblanchのものであるべき
                'nonce'=>$pstamp->getNonceAsU48()
            ];
    }
    $prop_tbl=new PropertiesTable($db);
    $properties=$prop_tbl->selectAllAsObject();    
    $r=[
        'settings'=>[
            'version'=>$properties->version,
            // 'god'=>$properties->god!=$properties->god?null:$properties->god,
            'pow_algorithm'=>$properties->pow_algorithm->pack(),
            'welcome'=>$properties->welcome,
            'json'=>[
                'jcs'=>$properties->json_jcs,
                'schema'=>json_decode($properties->json_schema)
            ]
        ],
        'chain'=>$chain_json,
        'account'=>$account_json
    ];
    (new SuccessResultResponseBuilder($r))->sendResponse();
}catch(ErrorResponseBuilder $exception){
    $exception->sendResponse();
}catch(Exception $e){
    ErrorResponseBuilder::catchException($e)->sendResponse();
}catch(Error $e){
    ErrorResponseBuilder::catchException($e)->sendResponse();
}

