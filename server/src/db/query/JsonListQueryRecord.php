<?php
namespace Jsonpost\db\query;
use Jsonpost\utils\UuidWrapper;
use PDO;
/**
 * json_storageをベースに、条件に合致した付帯情報を結合したレコードを生成して返します。
 */
class JsonListQueryRecord {
    //readonlyパラメータを宣言
    public $uuid_account;
    public $uuid_history;
    public $timestamp;
    public $hash;
    public $size;
    public $pow_score;
    public $pow_required;
    public function uuidHistoryAsText():string{
        return UuidWrapper::bin2text($this->uuid_history);
    }
    public function uuidAccountAsText():string{
        return UuidWrapper::bin2text($this->uuid_account);
    }

    // クエリメソッド
    /**
     * $valueにnullを設定できない問題があるぜ！
     */
    public static function query(PDO $db, int $offset, int $limit=-1, ?string $selector=null, ?string $value=null): array
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
                jhl.id_json_storage,
                h.id_account,
                h.timestamp,
                h.pow_score,
                h.pow_required
            FROM json_history_limited jhl
            JOIN history h ON jhl.id_history = h.id
        ),
        filtered_json_storage AS (
            SELECT
                hl.id_history,    
                hl.id_account,   -- 登録アカウントID
                hl.timestamp,    -- 登録日時
                hl.uuid_history, -- documentのuuidに相当
                js.hash,         -- documentのhash
                hl.pow_score,
                hl.pow_required,
                length(js.json) as size
            FROM json_storage js
            JOIN history_limited hl ON js.id = hl.id_json_storage  -- json_history_limitedの結果とjson_storageを結合
            $json_line
        ),
        history_limited2 AS (
            SELECT 
                a.uuid as uuid_account,
                jhs.uuid_history,
                jhs.timestamp,        
                jhs.hash,
                jhs.size,
                jhs.pow_score,
                jhs.pow_required
            FROM filtered_json_storage jhs
            JOIN account_root a ON jhs.id_account = a.id  -- json_history_limitedとhistoryをid_historyで結合
            ORDER BY jhs.uuid_history ASC  -- ここで並べ替えを行う
        )
        SELECT * FROM history_limited2;
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
        $records = [];
        while ($row = $stmt->fetchObject('Jsonpost\db\query\JsonListQueryRecord')) {
            $records[] = $row; // 直接オブジェクトとして取得
        }

        return $records; // 結果を返す
    }
}
