<?php
namespace Jsonpost\endpoint;

use Jsonpost\utils\ecdsasigner\PowStamp;
use Jsonpost\responsebuilder\ErrorResponseBuilder;

use Exception;




/**
 * 未初期化のStamp要求インスタンス。
 * PowStampの整合性だけを確認し、PowNonce,Nonceの検証を行いません。
 * nonceは0のみが許可されます。
 */
class RawStampRequiredEndpoint
{
    public readonly PowStamp $stamp;
    
    /**
     * HttpヘッダからPowStampV1ヘッダを読み出す。成功しない場合は適切な例外を搬出します。
     * @throws \Jsonpost\responsebuilder\ErrorResponseBuilder
     * @return PowStamp|null
     */
    public function __construct(?string $server_name,?string $rawData)
    {
        //スタンプの評価
        $powstamp1 = $_SERVER['HTTP_POWSTAMP_1'] ?? null;
        if($powstamp1==null){
            ErrorResponseBuilder::throwResponse(201);
        }
        $ps=null;
        try{
            $ps=new PowStamp(hex2bin($powstamp1));
        }catch(Exception $e){
            ErrorResponseBuilder::throwResponse(201);
        }
        // if($ps->getNonceAsInt()!=0){
        //     ErrorResponseBuilder::throwResponse(204,hint:[]);
        // }
        $verify_ret=false;
        try{
            $verify_ret=PowStamp::verify($ps,$server_name,$rawData);
        }catch(Exception $e){
            //詳細エラーが取れることある。
            ErrorResponseBuilder::throwResponse(203,$e->getMessage());
        }
        if(!$verify_ret){
            ErrorResponseBuilder::throwResponse(203);
        }
        $this->stamp=$ps;
    }

}
