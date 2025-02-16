<?php
/**
 * 初期状態のデータベースを作成します。１度だけ実行する必要があります。
 */
require_once dirname(__FILE__).'/../../src/config.php';
require_once dirname(__FILE__).'/../../src/db/tables.php';
require_once dirname(__FILE__).'/../../src/response_builder.php';

// use JsonPost;
use Jsonpost\{EcdasSignedAccountRoot,JsonStorage,JsonStorageHistory,PropertiesTable,DbSpecTable,Config,IResponseBuilder,ErrorResponseBuilder};


class SuccessResponseBuilder implements IResponseBuilder {
    
    public function __construct() {
    }

    public function sendResponse() {
        header('Content-Type: application/json');
        http_response_code(200);
        
        echo json_encode([
            'success' => true,
            'result'=>[]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}

class BenchmarkDatabaseSetup
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function setup(): IResponseBuilder
    {

        $this->db->exec('BEGIN IMMEDIATE');
        try{
            // テーブルがすでに存在するかを確認
            $checkSql = "SELECT name FROM sqlite_master WHERE type='table' AND name='properties';";
            $result = $this->db->query($checkSql);
            
            // properties テーブルが存在しない場合、初期化を実行
            if ($result->fetch()) {
                throw new ErrorResponseBuilder("Already initialized.");
            }
            $t1=new PropertiesTable($this->db);
            $t1->createTable();
            $t3=new JsonStorage($this->db);
            $t3->createTable();
            $t4=new EcdasSignedAccountRoot($this->db);
            $t4->createTable();
            $t5=new JsonStorageHistory($this->db);
            $t5->createTable();

            $t2=new DbSpecTable($this->db);
            $t2->createTable();

            $t1->insert('version','jp.nyatla:jsonpost:1');

            $t2->insert(JsonStorage::VERSION, $t3->name);
            $t2->insert(EcdasSignedAccountRoot::VERSION,$t4->name);
            $t2->insert(JsonStorageHistory::VERSION,$t5->name);
            $this->db->exec("COMMIT");
            return new SuccessResponseBuilder();
        }catch(ErrorResponseBuilder $e){
            $this->db->exec("ROLLBACK");
            return $e;
        }catch (Exception $e) {
            $this->db->exec("ROLLBACK");
            return new ErrorResponseBuilder($e->getMessage());
        }
    }
}

// SQLiteデータベースに接続
$db = Config::getRootDb();//new PDO('sqlite:benchmark_data.db');

// データベースのセットアップ
$setup = new BenchmarkDatabaseSetup($db);
$setup->setup()->sendResponse();

