<?php
namespace Jsonpost\db\query;
use Jsonpost\responsebuilder\ErrorResponseBuilder;
use Jsonpost\utils\UuidWrapper;
use PDO;
/**
 * json_storage_historyをキーにJson文章を返す。
 */
class RawJsonQueryRecord {
    //readonlyパラメータを宣言
    public $json;

    // クエリメソッド
    /**
     * $valueにnullを設定できない問題があるぜ！
     */
    public static function query(PDO $db, string $uuid): mixed
    {

        // SQL文を作成
        $sql = "
            SELECT 
                js.json
            FROM json_storage_history jsh
            JOIN json_storage js ON jsh.id_json_storage = js.id
            WHERE jsh.uuid = :uuid  -- UUIDでフィルタリング
        ";

        // クエリ実行の準備
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':uuid', $uuid, PDO::PARAM_LOB);

        
        // クエリ実行
        $stmt->execute();

        // 結果の処理
        $record=$stmt->fetchObject('Jsonpost\db\query\JsonListQueryRecord');
        if(false===$record){
            ErrorResponseBuilder::throwResponse(401);
        }
        return $record; // 結果を返す
    }
}
