<?php
namespace Jsonpost\db\query;
use Jsonpost\responsebuilder\ErrorResponseBuilder;
use Jsonpost\utils\UuidWrapper;
use PDO;
/**
 * json_storage_historyをキーにJson文章を返す。
 */
class StorageQueryRecord {
    //readonlyパラメータを宣言
    public string $uuid_account;
    public string $uuid_history;
    public int $timestamp;
    public string $powstampmsg;
    public string $json;
    public function uuidHistoryAsText():string{
        return UuidWrapper::bin2text($this->uuid_history);
    }
    public function uuidAccountAsText():string{
        return UuidWrapper::bin2text($this->uuid_account);
    }    
    public function powStampAsHex():string{
        return bin2hex($this->powstampmsg);
    }

    // クエリメソッド
    public static function query(PDO $db, string $uuid): mixed
    {

        // SQL文を作成
        $sql = "
            WITH json_reord AS (
                SELECT
                    jsh.id_history,
                    js.json,
                    jsh.uuid as uuid_history
                FROM json_storage_history jsh
                JOIN json_storage js ON jsh.id_json_storage = js.id
                WHERE jsh.uuid = :uuid  -- UUIDでフィルタリング
            ),
            history_joined AS (
                SELECT
                    jr.*,
                    h.id_account,
                    h.timestamp,
                    h.powstampmsg,
                    h.pow_required                  
                FROM json_reord jr
                JOIN history h ON h.id = jr.id_history
            ),
            full_rec AS (
                SELECT
                    hj.json,
                    a.uuid as uuid_account,
                    hj.uuid_history,
                    hj.timestamp,
                    hj.powstampmsg,
                    hj.pow_required           
                FROM history_joined hj
                JOIN account_root a ON hj.id_account = a.id
            )
            SELECT * FROM full_rec;
        ";

        // クエリ実行の準備
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':uuid', $uuid, PDO::PARAM_LOB);

        
        // クエリ実行
        $stmt->execute();

        // 結果の処理
        $record=$stmt->fetchObject('Jsonpost\db\query\StorageQueryRecord');
        if(false===$record){
            ErrorResponseBuilder::throwResponse(401);
        }
        return $record; // 結果を返す
    }
}
