<?php
namespace Jsonpost\endpoint;

use Jsonpost\db\tables\EcdasSignedAccountRootRecord;
use Jsonpost\db\tables\nst2024\{PropertiesTable,DbSpecTable};
use Jsonpost\db\tables\{EcdasSignedAccountRoot,JsonStorageHistory};

use Jsonpost\utils\ecdsasigner\PowStamp;
use Jsonpost\responsebuilder\ErrorResponseBuilder;
use Jsonpost\utils\pow\{TimeSizeDifficultyBuilder};

use Exception;
use PDO;

/**
 * Nonce=0を許容するエンドポイント。
 * PoWを含む他の確認はしない。
 * 未初期化インスタンスの認証向け。
 */
class ZeroStampEndpoint extends RawStampRequiredEndpoint
{
    private readonly PDO $db;
    private readonly PropertiesTable $pt;
    // public readonly Array $properties_records;
    /**
     * HttpヘッダからPowStampV1ヘッダを読み出す。成功しない場合は適切な例外を搬出します。
     * @throws \Jsonpost\responsebuilder\ErrorResponseBuilder
     * @return PowStamp|null
     */
    public readonly EcdasSignedAccountRootRecord $account;

    public function __construct(?string $server_name,?string $rawData)
    {   
        parent::__construct($server_name, $rawData);
        //powチェック
        if($this->stamp->getNonceAsInt()!=0){
            ErrorResponseBuilder::throwResponse(204,hint:[]);
        } 
    }
}