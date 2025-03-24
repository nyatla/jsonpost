<?php

use Jsonpost\endpoint\GodEndpoint;
use Jsonpost\utils\ecdsasigner\PowStamp2;


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
use Jsonpost\db\tables\{JsonStorageHistory,JsonStorage,EcdasSignedAccountRoot,OperationHistory,HistoryRecord};



class NodeFormatter{
    /**
     * Pow成功時の結果通知ノード
     * @param string $chain
     * @param string $hash
     * @param int $accept_nonce
     * @param int $require_nonce
     * @return array{domain: string, latest_hash: string, nonce: array{accept: int, required: int}}
     */
    public static function pow(string $chain,string $hash,int $accept_score,int $require_score,int $nonce){
        return [
            "chainId"=>$chain,#chainIDはアカウントIDと同じ
            "score"=>[
                "required"=>$require_score,
                "accept"=>$accept_score,
            ],
            "latest_hash"=>$hash,
            "latest_nonce"=>$nonce
        ];
    }
}

/**
 * operationhistoryに関わるバッチ
 * 
 */
function insertOperationSets(History $history_tbl,OperationHistory $oph_tbl,AccountBondEndpoint $endpoint,array $method_operation){
    $tbl_rec=$history_tbl->insert(
        $endpoint->accepted_time,
        $endpoint->account->id,
        $endpoint->stamp->stamp,
        0x0000ffffffffffff);
    for($i= 0;$i<count($method_operation);$i++){
        $oph_tbl->insert($tbl_rec->id,$method_operation[$i][0],$method_operation[$i][1]);
    }
}


/**
 * プロパティ変更後の同期
 * @param Jsonpost\db\tables\nst2024\PropertiesTable $tbl_properties
 * @return SuccessResultResponseBuilder
 */
function getPropertiesSet(OperationHistory $oph,PropertiesTable $tbl_properties):array{
    $tbl_properties->upsert(PropertiesTable::VNAME_GOD,$oph->getLatestByMethod(method: OperationHistory::METHOD_SET_GOD)->operationAsJson());
    $tbl_properties->upsert(PropertiesTable::VNAME_POW_ALGORITHM,$oph->getLatestByMethod(method: OperationHistory::METHOD_SET_POW_ALGORITHM)->operation);
    $tbl_properties->upsert(PropertiesTable::VNAME_WELCOME,$oph->getLatestByMethod(method: OperationHistory::METHOD_SET_WELCOME)->operationAsJson()?1:0);
    $tbl_properties->upsert(PropertiesTable::VNAME_JSON_SCHEMA,$oph->getLatestByMethod(method: OperationHistory::METHOD_SET_JSON_SCHEMA)->operationAsJson());
    $tbl_properties->upsert(PropertiesTable::VNAME_JSON_JCS,$oph->getLatestByMethod(method: OperationHistory::METHOD_SET_JSON_JCS)->operationAsJson()?1:0);

    $latest_property=$tbl_properties->selectAllAsObject();
    return [
        PropertiesTable::VNAME_WELCOME=>$latest_property->welcome,
        PropertiesTable::VNAME_GOD=>$latest_property->god,
        PropertiesTable::VNAME_POW_ALGORITHM=>$latest_property->pow_algorithm->pack(),
        PropertiesTable::VNAME_JSON_SCHEMA=>json_decode($latest_property->json_schema??''),
        PropertiesTable::VNAME_JSON_JCS=>$latest_property->json_jcs
    ];    
}



/**
 * version,params[server_name,pow_algolithm]は必須
 * params[welcome]はオプション
 * @param mixed $db
 * @param string $rawData
 * @return SuccessResultResponseBuilder
 */
function konnichiwa($db,string $rawData): IResponseBuilder
{
    // テーブルがすでに存在するかを確認
    $checkSql = "SELECT name FROM sqlite_master WHERE type='table' AND name='properties';";
    $result = $db->query($checkSql);
    
    // properties テーブルが存在しない場合、初期化を実行
    if(Config::isInitialized($db)){
        ErrorResponseBuilder::throwResponse(501);
    }

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
    foreach([PropertiesTable::VNAME_SEED_HASH,PropertiesTable::VNAME_POW_ALGORITHM]as $v){
        if(!array_key_exists($v,$request['params'])){
            ErrorResponseBuilder::throwResponse(302,"Parameter $.params.$v not found.");
        }
    } 
    $version = $request['version'];
    if($version!="urn::nyatla.jp:json-request::jsonpost-konnichiwa:1"){
        ErrorResponseBuilder::throwResponse(303,"Invalid version.");
    }
    //パラメタ
    $params=$request['params'];
    //Genesis
    $seed_hash=$params[PropertiesTable::VNAME_SEED_HASH];
    if (strlen($seed_hash) !== 64 || !ctype_xdigit($seed_hash)) {
        ErrorResponseBuilder::throwResponse(303,"Invalid genesis hash: must be a 64-character hexadecimal string.");
    }    

    //pow_algolithm
    $algorithm_name=$params[PropertiesTable::VNAME_POW_ALGORITHM];
    $pow_algorithm=null;
    try{
        $pow_algorithm=TimeSizeDifficultyBuilder::fromText($algorithm_name);
    }catch(Exception $e){
        ErrorResponseBuilder::throwResponse(303,"Unknown algorihm '$algorithm_name'");
    }

    //welcome デフォルト値有
    $welcome=$params[PropertiesTable::VNAME_WELCOME]??false;
    if(!is_bool($welcome)){
        ErrorResponseBuilder::throwResponse(303,'welcome must be boolean.');
    }
    //json_schema デフォルト値有
    $json_schema=$params[PropertiesTable::VNAME_JSON_SCHEMA]??null;
    if($json_schema!=null){
        $json_schema=json_encode($json_schema,JSON_UNESCAPED_UNICODE);
        //ここでバリデーションチェックかけると完璧
    }
    //json_jcs デフォルト値有
    $json_jcs=$params[PropertiesTable::VNAME_JSON_JCS]??false;
    if(!is_bool($json_jcs)){
        ErrorResponseBuilder::throwResponse(303,'json_jcs must be boolean.');
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
    $endpoint=ZeroNonceEndpoint::create($db,hex2bin($seed_hash),$rawData);    
    $pubkey_hex=bin2hex($endpoint->stamp->getEcdsaPubkey());
    
    #操作履歴を追加
    $tbl_properties->upsert(PropertiesTable::VNAME_VERSION,Config::VERSION);
    $tbl_properties->upsert(PropertiesTable::VNAME_SEED_HASH,$seed_hash);

    insertOperationSets($tbl_history,$tbl_operation_history,$endpoint,[
        [OperationHistory::METHOD_SET_GOD,$pubkey_hex],
        [OperationHistory::METHOD_SET_POW_ALGORITHM,$pow_algorithm->pack()],
        [OperationHistory::METHOD_SET_WELCOME,$welcome],
        [OperationHistory::METHOD_SET_JSON_JCS,$json_jcs],
        [OperationHistory::METHOD_SET_JSON_SCHEMA,$json_schema],
    ]);
    $rec=HistoryRecord::selectLatestAccountFirstHistory($db);
    $ps=$rec->powstampAsObject();
    return new SuccessResultResponseBuilder(
        [
            "properties"=>getPropertiesSet($tbl_operation_history,$tbl_properties),
            "chain"=>[
                "domain"=>"branch",
                "latest_hash"=>bin2hex($ps->getHash()),
                "nonce"=>$ps->getNonceAsU48(),
            ]            
        ]
    );
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
    // $opb=new OperationBatch($tbl_operation_history,$tbl_history);
    $oplist=[];


    //アルゴリズムの再設定
    $algorithm_name=$params[PropertiesTable::VNAME_POW_ALGORITHM] ??null;
    if($algorithm_name){
        try{
            $pow_algorithm=TimeSizeDifficultyBuilder::fromText($algorithm_name);
            array_push($oplist,[OperationHistory::METHOD_SET_POW_ALGORITHM,$pow_algorithm->pack()]);
        }catch(ErrorResponseBuilder $e){
            throw $e;
        }catch(Exception $e){
            ErrorResponseBuilder::throwResponse(103,"Unknown algorihm '$algorithm_name'");
        }
    }
    //Welcomeの再設定
    if (array_key_exists(PropertiesTable::VNAME_WELCOME, $params)) {
        $welcome = $params[PropertiesTable::VNAME_WELCOME];
        if(!is_bool($welcome)){
            ErrorResponseBuilder::throwResponse(303);
        }
        array_push($oplist,[ OperationHistory::METHOD_SET_WELCOME, $welcome]);
    }

    //json_schema デフォルト値有
    if (array_key_exists(PropertiesTable::VNAME_JSON_SCHEMA, $params)) {
        $p=$params[PropertiesTable::VNAME_JSON_SCHEMA];
        $v=$p==null?null:json_encode($p,JSON_UNESCAPED_UNICODE);
        array_push($oplist,[ OperationHistory::METHOD_SET_JSON_SCHEMA, $v]);
        //ここでバリデーションチェックかけると完璧
    }
    //json_jcs デフォルト値有
    if (array_key_exists(PropertiesTable::VNAME_JSON_JCS, $params)) {
        $v = $params[PropertiesTable::VNAME_JSON_JCS];
        if(!is_bool($v)){
            ErrorResponseBuilder::throwResponse(303,'json_jcs must be boolean.');
        }        
        array_push($oplist,[OperationHistory::METHOD_SET_JSON_JCS, $v]);
    }
    insertOperationSets($tbl_history,$tbl_operation_history,$endpoint,$oplist);

    return new SuccessResultResponseBuilder(
        [
            "properties"=>getPropertiesSet($tbl_operation_history,$tbl_properties),
            "chain"=>[
                "domain"=>"branch",
                "latest_hash"=>bin2hex($endpoint->stamp->getHash()),
                "nonce"=>$endpoint->stamp->getNonceAsU48(),
            ]
        ]
    );
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

