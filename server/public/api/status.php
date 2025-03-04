<?php
/**
 * POWSTAMPがある場合はaccountの情報も得られる。
 */




require dirname(__FILE__) .'/../../vendor/autoload.php'; // Composerでインストールしたライブラリを読み込む


use Jsonpost\Config;



use Jsonpost\responsebuilder\{IResponseBuilder,ErrorResponseBuilder,SuccessResultResponseBuilder};
use Jsonpost\db\tables\nst2024\{PropertiesTable};
use Jsonpost\db\tables\{EcdasSignedAccountRoot,JsonStorageHistory};



use Jsonpost\endpoint\{
    Jsonpost\endpoint\StampRequiredEndpoint
};


$db = Config::getRootDb();//new PDO('sqlite:benchmark_data.db');
try{
    //前処理
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        ErrorResponseBuilder::throwResponse(err_code: 101,status:405);
    }
    $prop_tbl=new PropertiesTable($db);
    $properties=$prop_tbl->selectAllAsObject();
    $account_block=null;
    if(isset($_SERVER['HTTP_POWSTAMP_1'])){
        //スタンプがついてたらaccountの情報も取る
        $endpoint=new StampRequiredEndpoint(server_name: $properties->server_name,rawData: null);
        #ここに段階右折してるから後で直して
        $act=new EcdasSignedAccountRoot($db);
        $act_rec=$act->selectAccountByPubkey($endpoint->stamp->getEcdsaPubkey());
        $acth=new JsonStorageHistory($db);
        $acth_rec=$acth->selectLatestByAccount($act_rec->id);
        $account_block=[
            'uuid'=>$act_rec->uuidAsText(),
            'latest_pow_time'=>$acth_rec->created_date
        ];
    }

    $r=[
        'welcome'=>[
            'version'=>$properties->version,
            'server_name'=>$properties->server_name,
            'pow_algorithm'=>$properties->pow_algorithm,
        ],
        'root'=>[
            'latest_pow_time'=>$properties->root_pow_accept_time,
        ],
        'account'=>$account_block
    ];
    (new SuccessResultResponseBuilder($r,JSON_PRETTY_PRINT))->sendResponse();
}catch(ErrorResponseBuilder $exception){
    $exception->sendResponse();
}catch(Exception $e){
    ErrorResponseBuilder::catchException($e)->sendResponse();
}catch(Error $e){
    ErrorResponseBuilder::catchException($e)->sendResponse();
}

