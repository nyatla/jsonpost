<?php
require_once dirname(__FILE__).'/../libs/db/tables.php';


class BenchmarkDatabaseSetup
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function setup(): bool
    {

        $this->db->beginTransaction();
        try{
            // テーブルがすでに存在するかを確認
            $checkSql = "SELECT name FROM sqlite_master WHERE type='table' AND name='properties';";
            $result = $this->db->query($checkSql);
            
            // properties テーブルが存在しない場合、初期化を実行
            if ($result->fetch()) {
                $this->db->rollBack();
                return false;
            }
            $t1=new PropertiesTable($this->db);
            $t1->createTable();
            $t1->insert('version','jp.nyatla:llm-benchmark:1');
            $t3=new JsonStorage($this->db);
            $t3->createTable();
            $t4=new EcdasSignedAccountRoot($this->db);
            $t4->createTable();
            $t5=new JsonStorageHistory($this->db);
            $t5->createTable();

            $t2=new DbSpecTable($this->db);
            $t2->createTable();
            $t2->insert(JsonStorage::VERSION, $t3->name);
            $t2->insert(EcdasSignedAccountRoot::VERSION,$t4->name);
            $t2->insert(JsonStorageHistory::VERSION,$t5->name);
            $this->db->commit();
            return true;
        }catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
}

// SQLiteデータベースに接続
$db = new PDO('sqlite:benchmark_data.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// データベースのセットアップ
$setup = new BenchmarkDatabaseSetup($db);
if($setup->setup()){
    echo 'データベースを生成しました。';
}else{
    echo '初期化済です。';
}

?>