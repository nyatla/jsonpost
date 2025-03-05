<?php
namespace Jsonpost\db\query;
use PDO;
/**
 * JsonListQueryRecordのマッチした件数のみを返すクエリです。
 */
class JsonCountQueryRecord {
    //readonlyパラメータを宣言
    public $matched;

    
    // クエリメソッド
    /**
     * $valueにnullを設定できない問題があるぜ！
     */
    public static function query(PDO $db, int $offset, int $limit=-1, ?string $selector=null, ?string $value=null): JsonCountQueryRecord
    {
        $json_line='';
        if($selector!=null){
            if($value!=null){
                $json_line='WHERE json_extract(js.json, :selector) == :value';
            }else{
                $json_line='WHERE json_extract(js.json, :selector) IS NOT NULL';
            }
        }


        // SQL文を作成
        $sql = "
        WITH json_history_limited AS (
            SELECT *
            FROM json_storage_history jsh
            ORDER BY jsh.id_history ASC
            LIMIT :limit OFFSET :offset
        ),
        history_limited AS (
            SELECT 
                jhl.id_history,
                jhl.uuid AS uuid_history,
                jhl.id_json_storage
            FROM json_history_limited jhl
            JOIN history h ON jhl.id_history = h.id
        ),
        filtered_json_storage AS (
            SELECT
                hl.id_history
            FROM json_storage js
            JOIN history_limited hl ON js.id = hl.id_json_storage  -- json_history_limitedの結果とjson_storageを結合
            $json_line
        )
        SELECT count(*) as matched FROM filtered_json_storage;
        ";

        // クエリ実行の準備
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        if($selector!=null){
            $stmt->bindParam(':selector', $selector, PDO::PARAM_STR);
            if($value!=null){
                $stmt->bindParam(':value', $value, PDO::PARAM_STR);
            }
        }

        // クエリ実行
        $stmt->execute();

        // 結果の処理
        $rec=$stmt->fetchObject('Jsonpost\db\query\JsonCountQueryRecord');
        if($rec===false){
            throw new \Exception('SQL error');
        }
        return $rec; // 結果を返す
    }
}
