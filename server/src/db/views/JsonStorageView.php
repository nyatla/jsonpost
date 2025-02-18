<?php
namespace Jsonpost\db\views;

use \PDO as PDO;
class JsonStorageView
{
    public const VERSION = 'JsonStorageView:1';
    private $db;
    public readonly string $name;

    public function __construct($db, $name = "view_json_storage")
    {
        $this->name = $name;
        $this->db = $db;
    }

    // ビューを作成する
    public function createView()
    {
        //idの順位とcreated_dateの順位が同じと期待して、MIN(id)を使う。
        $sql = "
        CREATE VIEW IF NOT EXISTS $this->name AS
        SELECT 
        js.id,
        jsh.created_date, 
        js.uuid, 
        js.hash 
        FROM json_storage_history jsh
        JOIN json_storage js ON jsh.id_json_storage = js.id
        WHERE jsh.id = (
            SELECT MIN(id) 
            FROM json_storage_history 
            WHERE id_json_storage = jsh.id_json_storage
        );";

        $this->db->exec($sql);
    }
    /**
     * created_dateでソートしたテーブルのindex番目からlimit個を単純配列の配列にして返す。
     * @param int $index
     * @param int $limit
     * @return void
     */
    public function selectByIndex(int $index, int $limit): array
    {
        // 1つ目のクエリ：レコードのデータ
        $sql = "
        SELECT 
            id,
            created_date, 
            uuid, 
            hash
        FROM $this->name
        ORDER BY created_date
        LIMIT :limit OFFSET :index;
        ";
        // レコードデータの取得
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':index', $index, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_NUM);  // 単純配列の配列
        // 2つ目のクエリ：総レコード数
        $countSql = "SELECT COUNT(*) FROM $this->name;";       
        // 総レコード数の取得
        $stmtCount = $this->db->query($countSql);
        $total = $stmtCount->fetchColumn();
    
        // 結果を返す
        return [
            'items' => $items,
            'total' => (int)$total,  // レコードの総数
        ];
    }

    /**
     * レコードの値がuuidと一致するレコードのindexを計算し、そのindexよりも大きいレコードをcreated_dateでソートしたものから、limit個を単純配列の配列にして返す。
     * @param int $uuid
     * @param int $limit
     * @return void
     */
    public function selectByUuid(string $uuid, int $limit): array
    {
        // 最初に、uuidが一致するレコードのindexを取得する
        $indexSql = "
        SELECT id
        FROM $this->name
        WHERE hex(uuid) == :uuid
        LIMIT 1;
        ";
        // echo(strtoupper(bin2hex($uuid)));        
        // $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $stmt = $this->db->prepare($indexSql);
        $t=strtoupper(bin2hex($uuid));
        $stmt->bindValue(':uuid', $t, PDO::PARAM_STR);
        // $stmt->debugDumpParams();
        $stmt->execute();
        // $stmt=$this->db->query("select created_date from json_storage where hex(uuid)='019511DDE5B9724E9D01632FB115F0B3';");
        // uuidが一致するレコードが見つかれば、インデックスを取得
        $index = $stmt->fetchColumn();
        $items=[];
        if ($index !== false) {
            // uuidのindexよりも大きいレコードをcreated_dateでソートして、limit個取得する
            $sql = "
            SELECT id,created_date, uuid, hash
            FROM $this->name
            WHERE id >= :index
            ORDER BY id
            LIMIT :limit;
            ";
        
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':index', $index, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_NUM);  // 単純配列の配列
        }
            // 2つ目のクエリ：総レコード数
        $countSql = "SELECT COUNT(*) FROM $this->name;";       
        // 総レコード数の取得
        $stmtCount = $this->db->query($countSql);
        $total = $stmtCount->fetchColumn();
    
    
        // 結果を返す
        return [
            'items' => $items,
            'total' => (int)$total,  // レコードの総数
        ];
    }
}