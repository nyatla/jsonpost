<?php
namespace Jsonpost\db\tables;

use \PDO as PDO;
use Exception;
use \Jsonpost\utils\UuidWrapper;
use \Jsonpost\responsebuilder\ErrorResponseBuilder;



class JsonStorageRecord {

    public int $id;    
    public string $hash;
    public string $json;
    public readonly int $is_new_record;
    public function __construct($is_new_record){
        $this->is_new_record = $is_new_record;
    }

}
class JsonStorage
{
    public const VERSION='JsonStorage:1';

    private $db;
    public readonly string $name;

    public function __construct($db,$name="json_storage")
    {
        $this->db = $db;
        $this->name=$name;
    }


    // テーブル作成
    public function createTable():JsonStorage
    {
        $sql = "
        CREATE TABLE IF NOT EXISTS $this->name (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            hash BLOB NOT NULL UNIQUE,         -- [RO]識別子/文章ハッシュ jsonの内容から作成したsha256
            json JSON NOT NULL                 -- [RO]実際のJSONデータ(そのまま保存)
        )";
        $this->db->exec($sql);
        return $this;
    }

    // jsonDataのSHA-256ハッシュを取り、そのハッシュを使ってUUID5を生成（URL名前空間使用）
    public function selectOrInsertIfNotExist(string $jsonData):JsonStorageRecord
    {
        // jsonDataのSHA-256ハッシュを計算
        $hash = hex2bin(hash('sha256', $jsonData)); // JSONデータのSHA-256ハッシュ

        // まず既存のレコードを検索
        $sql = "SELECT * FROM $this->name WHERE hash = :hash";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':hash', $hash, PDO::PARAM_LOB);
        $stmt->execute();
        $record = $stmt->fetchObject('Jsonpost\db\tables\JsonStorageRecord',[false]);

        if ($record) {
            // レコードが存在すれば返す
            return $record;
        } else {
            // レコードが存在しなければ挿入
            $sqlInsert = "
            INSERT INTO $this->name (hash, json)
            VALUES (:hash, json(:json))
            ";
            $stmtInsert = $this->db->prepare($sqlInsert);
            $stmtInsert->bindParam(':hash', $hash, PDO::PARAM_LOB);
            $stmtInsert->bindParam(':json', $jsonData);
            $stmtInsert->execute();

            // 挿入したレコードのIDを取得
            $insertedId = $this->db->lastInsertId();

            // 新しく挿入したレコードを取得（IDを使って取得）
            $sql = "SELECT * FROM $this->name WHERE id = ? LIMIT 1;";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$insertedId]);
            
            // 新しく挿入したレコードを返す
            return $stmt->fetchObject('Jsonpost\db\tables\JsonStorageRecord',[true]);
        }
    }
    public function selectById(int $id): JsonStorageRecord
    {
        // 最初に、idが一致するレコードのindexを取得する
        $indexSql = "SELECT * FROM $this->name WHERE id == :id;";
        // echo(strtoupper(bin2hex($uuid)));        
        // $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $stmt = $this->db->prepare($indexSql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        // $stmt->debugDumpParams();
        $stmt->execute();

        // 結果を取得
        $result = $stmt->fetchObject('Jsonpost\db\tables\JsonStorageRecord',[false]);

        // レコードが存在しない場合は例外をスロー
        if ($result===false) {
            ErrorResponseBuilder::throwResponse(401);
        }
        return $result;
    }


    
    public function countWithFilter(int $index, ?int $limit, ?string $path, ?string $value): array
    {
        // 絞り込み条件の追加（$path と $value が指定されている場合）
        $filterCondition = '';
        $params = [
            ':index' => $index,
            ':limit' => $limit ?? -1
        ];
        // $path と $value が指定されている場合、json_extractで絞り込み
        if ($path && $value !== null) {
            $filterCondition = " AND json_extract(json, :selector) = :value ";
            $params[':selector'] = $path;
            $params[':value'] = $value;
        } elseif ($path) {
            // $path が指定されているが、$value が指定されていない場合
            $filterCondition = " AND json_extract(json, :selector) IS NOT NULL ";
            $params[':selector'] = $path;
        } elseif ($value) {
            throw new Exception('Must be set path with value.');
        }
        // まず、指定された範囲内のレコードを絞り込み、総数を数える
        $countSqlTotal = "SELECT COUNT(*) FROM (SELECT id FROM $this->name ORDER BY id LIMIT :limit OFFSET :index)";
        $stmt = $this->db->prepare($countSqlTotal);
        $stmt->bindValue(':index', $params[':index']);
        $stmt->bindValue(':limit', $params[':limit']);
        $stmt->execute();
        $total = $stmt->fetchColumn();
        $matched=$total;//検索式がない場合は全てマッチ
        if(isset($params[':selector'])){
            // 次に、範囲内のレコードのうち、絞り込み条件に合致するレコード数をカウント
            $countSqlMatched = "SELECT COUNT(*) FROM (SELECT id FROM (SELECT * FROM $this->name ORDER BY id LIMIT :limit OFFSET :index) WHERE 1=1 $filterCondition)";
            $stmt = $this->db->prepare($countSqlMatched);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $matched = $stmt->fetchColumn();
        }

    
    
        // 結果を返す
        return [
            'matched' => (int)$matched,  // ページ内で絞り込んだレコード数（条件に合致したレコード数）
            'total' => (int)$total,      // ページ内で絞り込んだレコード総数
        ];
    }

}