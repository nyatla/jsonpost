<?php



/**
 * 所定の書式に格納したJSONファイルのアップロードを受け付けます。
 * 
 */
require dirname(__FILE__) .'/../../vendor/autoload.php'; // Composerでインストールしたライブラリを読み込む


// use JsonPost;
use Jsonpost\Config;
use Jsonpost\responsebuilder\{IResponseBuilder,ErrorResponseBuilder,SuccessResultResponseBuilder};
use Jsonpost\endpoint\{PoWAccountRequiredEndpoint};
use Jsonpost\db\tables\{JsonStorageHistory,JsonStorage,History};
use Jsonpost\utils\{JCSValidator};

use Opis\JsonSchema\{Validator, Errors\ErrorFormatter};


class JcsLikeJsonEncoder
{
    /**
     * JCSライクJSONエンコード（連想配列のキーのみソート）
     *
     * @param mixed $data
     * @return string
     */
    public function encode($data): string
    {
        $sortedData = $this->sortAssocRecursive($data);
        return json_encode($sortedData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * 連想配列のキーを再帰的にUTF-8バイト順でソート
     *
     * @param mixed $data
     * @return mixed
     */
    private function sortAssocRecursive($data)
    {
        if (is_array($data) && $this->isAssocArray($data)) {
            $keys = array_keys($data);
            usort($keys, 'strcmp');  // UTF-8バイト順ソート（PHP標準関数）

            $sorted = [];
            foreach ($keys as $key) {
                $sorted[$key] = $this->sortAssocRecursive($data[$key]);
            }
            return $sorted;
        } elseif (is_array($data)) {
            // 通常の配列（リスト）は順序そのまま
            return array_map([$this, 'sortAssocRecursive'], $data);
        }
        return $data;
    }

    /**
     * 連想配列かどうかを判定
     *
     * @param array $array
     * @return bool
     */
    private function isAssocArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
}



// アップロードAPIの処理
function upload($db,$rawData):IResponseBuilder
{

    $endpoint=PoWAccountRequiredEndpoint::create($db,$rawData);
    //書き込むデータは、JCSの場合はそのまま、そうでなければソートしたJCSライクなJSON

    $upload_data=null;
    //JCSチェック
    if($endpoint->properties->json_jcs){
        $v=new JCSValidator();
        try{
            $v->isJcsToken($rawData);
        }catch(\Exception $e){
            $m=$e->getMessage();
            ErrorResponseBuilder::throwResponse(301,"Not JCS compatible.:$m");
        }
        $upload_data=$rawData;
    }
    //JSONスキーマチェック
    if($endpoint->properties->json_schema!=null){
        // JSONスキーマとデータをデコード
        $schema = $endpoint->properties->json_schema;
        $data=json_decode($rawData);
        // JSONスキーマの検証
        $validator = new Validator();
        $validator->setStopAtFirstError(true);
        $vret = $validator->validate($data, $schema);
        if ($vret->isValid()=== false) {
            $errors=(new ErrorFormatter())->formatFlat($vret->error());
            $em=$errors[0];
            ErrorResponseBuilder::throwResponse(301,"Json scheam not valid.:$em");
        }
        if($upload_data==null){
            $jle=new JcsLikeJsonEncoder();
            $upload_data=$jle->encode($data);
        }    
    }else{
        $data = json_decode($rawData, true);
        if ($data === null) {
            ErrorResponseBuilder::throwResponse(301,'Invalid JSON format.');
        }
        if($upload_data==null){
            $jle=new JcsLikeJsonEncoder();
            $upload_data=$jle->encode(data: $data);
        }
    }
    assert($upload_data!=null);
    


    //ここから書込み系の
    $ar_rec=$endpoint->account;

    //アップデートのバッチ処理
    $js_tbl=new JsonStorage($db);
    $hs_tbl=new History($db);
    $jsh_table=new JsonStorageHistory($db);
    $js_rec=$js_tbl->selectOrInsertIfNotExist($upload_data);
    // $js_rec=$js_tbl->selectOrInsertIfNotExist($rawData);
    $hs_rec=$hs_tbl->insert($endpoint->accepted_time,$ar_rec->id,$endpoint->stamp->stamp,$endpoint->required_pow);
    $jsh_rec=$jsh_table->insert($hs_rec->id,$js_rec->id);    
    //アップデートのバッチ処理/


    $endpoint->commitStamp();

    return new SuccessResultResponseBuilder(
        [
        'document'=>[
            'status'=>$js_rec->is_new_record?'new':'copy',
            'json_uuid'=>$jsh_rec->uuidAsText(),
        ],
        'account'=>[
            'status'=>$ar_rec->is_new_record?'new':'exist',
            'user_uuid'=>$ar_rec->uuidAsText(),
            'nonce'=>$endpoint->next_nonce,    
        ],
        'pow'=>[
            'domain'=>$ar_rec->is_new_record?'root':'account',
            'required'=>$endpoint->required_pow,
            'accepted'=>$endpoint->accepted_pow
        ]
        ]);
}

$db = Config::getRootDb();//new PDO('sqlite:benchmark_data.db');

$db->exec('BEGIN IMMEDIATE');
try{
    //前処理
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ErrorResponseBuilder::throwResponse(101,status:405);
    }
    
    // POSTリクエストからJSONデータを取得
    $rawData = file_get_contents('php://input');
    // $rawData = file_get_contents('./upload_test.json');
    
    if(strlen($rawData)>Config::MAX_JSON_SIZE){
        ErrorResponseBuilder::throwResponse(304);
    }

    // アップロードAPI処理を呼び出す
    upload($db, $rawData)->sendResponse();
    $db->exec("COMMIT");
}catch(ErrorResponseBuilder $e){
    $db->exec("ROLLBACK");
    $e->sendResponse();
    throw $e;
}catch(Exception $e){
    $db->exec("ROLLBACK");
    ErrorResponseBuilder::catchException($e)->sendResponse();
    throw $e;
}catch(Error $e){
    $db->exec("ROLLBACK");
    ErrorResponseBuilder::catchException($e)->sendResponse();
    throw $e;
}



