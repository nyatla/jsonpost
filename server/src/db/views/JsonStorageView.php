<?php
namespace Jsonpost\db\views;

use Jsonpost\utils\UuidWrapper;
use \PDO as PDO;
use Exception;
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
        js.hash,
        js.json
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
     * 指定されたインデックス番号から、指定された件数のデータを取得し、さらに JSON カラムの絞り込みを行う関数です。
     *
     * @param int $index    取得を始めるインデックス番号。例えば、最初の100件を取得する場合は 0 になります。
     * @param int $limit    取得するレコード数。例えば、100件の場合は 100 を指定します。
     * @param string|null $path  絞り込み対象の JSON パス。例えば '$.status' などの形式で指定します。
     * @param string|null $value     JSON パスで取得した値が一致するレコードを絞り込みます。NULLの場合はそのキーが存在するレコードを取得します。
     *
     * @return array 絞り込んだレコードと、総レコード数を含む連想配列を返します。
     *              - 'items': 絞り込まれたレコードの配列
     *              - 'total': 総レコード数（絞り込み前）
     */
    public function selectByIndexWithFilter(int $index, int $limit, ?string $path, ?string $value): array
    {
        // 絞り込み条件の追加（$selector と $value が指定されている場合）
        $filterCondition = '';
        $params = [
            ':limit' => $limit,
            ':index' => $index
        ];
    
        // $selector と $value が指定されている場合、json_extractで絞り込み
        if ($path && $value !== null) {
            $filterCondition = " AND json_extract(json, :selector) = :value ";
            $params[':selector'] = $path;
            $params[':value'] = $value;
        } elseif ($path) {
            // $selector が指定されているが、$value が指定されていない場合
            $filterCondition = " AND json_extract(json, :selector) IS NOT NULL ";
            $params[':selector'] = $path;
        }elseif($value){
            throw new Exception('Must be set path with value.');
        }
    
        // 1つ目のクエリ：レコードのデータ（絞り込み条件を追加）
        $sql = "
        SELECT 
            id,
            created_date, 
            uuid, 
            hash
        FROM (SELECT * FROM $this->name ORDER BY id LIMIT :limit OFFSET :index)
        WHERE 1=1 $filterCondition;";
    
        // レコードデータの取得
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_NUM);  // 配列形式で取得（jsonデータも含む）
    
        // 2つ目のクエリ：総レコード数（絞り込み条件を追加）
        $countSql = "SELECT COUNT(id) FROM $this->name";
        $stmtCount = $this->db->prepare($countSql);
        $stmtCount->execute();
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
    public function selectByUuid(string $uuid, int $limit, ?string $selector, ?string $value): array
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
        if ($index === false) {
            $u=UuidWrapper::bin2text($uuid);
            throw new Exception("Invalid uuid {$u}");
        }
        return $this->selectByIndexWithFilter($index,$limit,$selector,$value);
    }
}