<?php

use Jsonpost\endpoint\GodEndpoint;


require dirname(__FILE__) .'/../../vendor/autoload.php'; // Composerでインストールしたライブラリを読み込む

/**
 * 初期状態のデータベースを作成します。１度だけ実行する必要があります。
 */


// use JsonPost;
use Jsonpost\Config;
use Jsonpost\db\tables\History;
use Jsonpost\utils\pow\TimeSizeDifficultyBuilder;
use Jsonpost\responsebuilder\{IResponseBuilder,ErrorResponseBuilder,SuccessResultResponseBuilder};
use Jsonpost\endpoint\{ZeroNonceEndpoint,AccountBondEndpoint};
use Jsonpost\db\tables\nst2024\{PropertiesTable,DbSpecTable};
use Jsonpost\db\tables\{JsonStorageHistory,JsonStorage,EcdasSignedAccountRoot,OperationHistory};





/**
 * operationhistoryに関わるバッチ
 * 
 */
class OperationBatch{
    private History $history_tbl;
    private OperationHistory $oph_tbl;
    public function __construct(OperationHistory $oph_tbl,History $history_tbl) {
        $this->history_tbl = $history_tbl;
        $this->oph_tbl=$oph_tbl;
    }
    /**
     * 操作履歴を投入するバッチ
     * @param string $method
     * @param mixed $operation
     * @return void
     */
    public function insertOperationSet(AccountBondEndpoint $endpoint,string $method,mixed $operation){
        $tbl_rec=$this->history_tbl->insert(
            $endpoint->accepted_time,
            $endpoint->account->id,
            $endpoint->stamp->getPowScore32(),
            0xffffffff);
        $this->oph_tbl->insert($tbl_rec->id,$method,$operation);
    }
    public function copyToPropertyTable(PropertiesTable $tbl){
        $tbl->upsert(PropertiesTable::VNAME_GOD,json_decode($this->oph_tbl->getLatestByMethod(method: OperationHistory::METHOD_SET_GOD)->operation)[0]);
        $tbl->upsert(PropertiesTable::VNAME_POW_ALGORITHM,$this->oph_tbl->getLatestByMethod(method: OperationHistory::METHOD_SET_POW_ALGORITHM)->operation);
        $tbl->upsert(PropertiesTable::VNAME_SERVER_NAME,json_decode($this->oph_tbl->getLatestByMethod(method: OperationHistory::METHOD_SET_SERVER_NAME)->operation)[0]);
    }
}


function konnichiwa($db,string $rawData): IResponseBuilder
{
    //JSONペイロードの評価
    $request = json_decode($rawData, true);
    if ($request === null) {
        ErrorResponseBuilder::throwResponse(301,'Invalid JSON format.');
    }
    foreach(['version','params']as $v){
        if(!array_key_exists($v,$request)){
            ErrorResponseBuilder::throwResponse(302,"Parameter $.$v not found.");
        }
    } 
    foreach([PropertiesTable::VNAME_SERVER_NAME,PropertiesTable::VNAME_POW_ALGORITHM]as $v){
        if(!array_key_exists($v,$request['params'])){
            ErrorResponseBuilder::throwResponse(302,"Parameter $.params.$v not found.");
        }
    } 
    $version = $request['version'];
    $params=$request['params'];
    if($version!="urn::nyatla.jp:json-request::jsonpost-konnichiwa:1"){
        ErrorResponseBuilder::throwResponse(303,"Invalid version.");
    }
    $server_name=$param[PropertiesTable::VNAME_SERVER_NAME]??null;
    $algorithm_name=$params[PropertiesTable::VNAME_POW_ALGORITHM] ??null;
    if(!$algorithm_name){
        ErrorResponseBuilder::throwResponse(302,'Pow algorithm not set.');
    }
    $pow_algorithm=null;
    try{
        $pow_algorithm=TimeSizeDifficultyBuilder::fromText($algorithm_name);
    }catch(Exception $e){
        ErrorResponseBuilder::throwResponse(303,"Unknown algorihm '$algorithm_name'");
    }
    // テーブルがすでに存在するかを確認
    $checkSql = "SELECT name FROM sqlite_master WHERE type='table' AND name='properties';";
    $result = $db->query($checkSql);
    
    // properties テーブルが存在しない場合、初期化を実行
    if ($result->fetch()) {
        ErrorResponseBuilder::throwResponse(501);
    }
    //テーブルの生成
    $tbl_properties=(new PropertiesTable($db))->createTable();
    $tb_dbspec=(new DbSpecTable($db))->createTable();

    $tbl_account=(new EcdasSignedAccountRoot($db))->createTable();
    $tbl_jsonstorage=(new JsonStorage($db))->createTable();
    $tbl_jsonstorage_history=(new JsonStorageHistory($db))->createTable();
    $tbl_history=(new History($db))->createTable();
    $tbl_operation_history=(new OperationHistory($db))->createTable();
    //テーブルの登録情報
    $tb_dbspec->insert(EcdasSignedAccountRoot::VERSION,$tbl_account->name);
    $tb_dbspec->insert(JsonStorage::VERSION, $tbl_jsonstorage->name);
    $tb_dbspec->insert(JsonStorageHistory::VERSION,$tbl_jsonstorage_history->name);
    $tb_dbspec->insert(History::VERSION,$tbl_history->name);
    $tb_dbspec->insert(OperationHistory::VERSION,$tbl_operation_history->name);

    
    //エンドポイントの生成（仮登録してgodエンドポイントで実行したほうがいいかもね
    $endpoint=ZeroNonceEndpoint::create($db,$server_name,$rawData);    
    $pubkey_hex=bin2hex($endpoint->stamp->getEcdsaPubkey());


    #操作履歴を追加
    $opb=new OperationBatch($tbl_operation_history,$tbl_history);
    $opb->insertOperationSet($endpoint,OperationHistory::METHOD_SET_GOD,[$pubkey_hex]);
    $opb->insertOperationSet($endpoint,OperationHistory::METHOD_SET_SERVER_NAME,[$server_name]);
    $opb->insertOperationSet($endpoint,OperationHistory::METHOD_SET_POW_ALGORITHM,$pow_algorithm->pack());
    // #デバック用
    // $opb->insertOperationSet($endpoint,OperationHistory::METHOD_SET_POW_ALGORITHM,$pow_algorithm->pack());

    
    $tbl_properties->upsert(PropertiesTable::VNAME_VERSION,Config::VERSION);
    $tbl_properties->upsert(PropertiesTable::VNAME_ROOT_POW_ACCEPT_TIME,0);     //ルートのPOWリクエスト受理時刻
    $opb->copyToPropertyTable($tbl_properties);//操作履歴を反映

    $latest_property=$tbl_properties->selectAllAsObject();


    return new SuccessResultResponseBuilder(
        [
            PropertiesTable::VNAME_GOD=>$latest_property->god,
            PropertiesTable::VNAME_SERVER_NAME=>$latest_property->server_name,
            PropertiesTable::VNAME_POW_ALGORITHM=>$latest_property->pow_algorithm->pack(),
        ]);
}


function setparams($db,string $rawData): IResponseBuilder
{
    //GodOperationか確認
    $endpoint=GodEndpoint::create($db,$rawData);

    
    //JSONペイロードの評価
    $request = json_decode($rawData, true);
    if ($request === null) {
        ErrorResponseBuilder::throwResponse(301,'Invalid JSON format.');
    }
    foreach(['version','params']as $v){
        if(!array_key_exists($v,$request)){
            ErrorResponseBuilder::throwResponse(302,"Parameter $.$v not found.");
        }
    } 
    //versionチェック
    $version = $request['version'];
    $params=$request['params'];
    if($version!="urn::nyatla.jp:json-request::jsonpost-setparams:1"){
        ErrorResponseBuilder::throwResponse(303,"Invalid version.");
    }

    $tbl_operation_history=new OperationHistory($db);
    $tbl_history=(new History($db));
    $tbl_properties=(new PropertiesTable($db));
    $opb=new OperationBatch($tbl_operation_history,$tbl_history);

    //アルゴリズムの再設定
    $algorithm_name=$params[PropertiesTable::VNAME_POW_ALGORITHM] ??null;
    if($algorithm_name){
        try{
            $pow_algorithm=TimeSizeDifficultyBuilder::fromText($algorithm_name);
            $opb->insertOperationSet($endpoint,OperationHistory::METHOD_SET_POW_ALGORITHM,$pow_algorithm->pack());
        
        }catch(Exception $e){
            ErrorResponseBuilder::throwResponse(303,"Unknown algorihm '$algorithm_name'");
        }
    }
    if (array_key_exists(PropertiesTable::VNAME_SERVER_NAME, $params)) {
        $server_name = $params[PropertiesTable::VNAME_SERVER_NAME];
        $opb->insertOperationSet($endpoint, OperationHistory::METHOD_SET_SERVER_NAME, [$server_name]);
    }
    
    $opb->copyToPropertyTable($tbl_properties);//操作履歴を反映
    $latest_property=$tbl_properties->selectAllAsObject();
 

    return new SuccessResultResponseBuilder(
        [
            PropertiesTable::VNAME_GOD=>$latest_property->god,
            PropertiesTable::VNAME_SERVER_NAME=>$latest_property->server_name,
            PropertiesTable::VNAME_POW_ALGORITHM=>$latest_property->pow_algorithm->pack(),
        ]);
}


// SQLiteデータベースに接続
$db = Config::getRootDb();//new PDO('sqlite:benchmark_data.db');
$db->exec('BEGIN IMMEDIATE');
try{
    switch($_SERVER['QUERY_STRING']){
        case 'konnichiwa':{
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                ErrorResponseBuilder::throwResponse(101,status:405);
            }
            // POSTリクエストからJSONデータを取得
            $rawData = file_get_contents('php://input');
            // $rawData = file_get_contents('./upload_test.json');
            if(strlen($rawData)>2048){
                ErrorResponseBuilder::throwResponse(301,"upload data too large.",413);
            }
            // データベースのセットアップ            
            konnichiwa($db,$rawData)->sendResponse();
            break;
        }
        case 'setparams':{
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                ErrorResponseBuilder::throwResponse(101,status:405);
            }
            $rawData = file_get_contents('php://input');
            // $rawData = file_get_contents('./upload_test.json');
            if(strlen($rawData)>2048){
                ErrorResponseBuilder::throwResponse(301,"upload data too large.",413);
            }
            setparams($db,$rawData)->sendResponse();
            break;

        }
        default:{
            ErrorResponseBuilder::throwResponse(101,"The door to heaven is closed. ");
        }
    }
    $db->exec("COMMIT");
}catch(ErrorResponseBuilder $e){
    $db->exec("ROLLBACK");
    $e->sendResponse();
    throw $e;
}catch (Exception $e) {
    $db->exec("ROLLBACK");
    ErrorResponseBuilder::catchException($e)->sendResponse();
    throw $e;
}catch(Error $e){
    ErrorResponseBuilder::catchException($e)->sendResponse();
    throw $e;
}

