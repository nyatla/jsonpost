<?php
namespace Jsonpost\endpoint;

use Jsonpost\utils\ecdsasigner\PowStamp2;
use Jsonpost\responsebuilder\ErrorResponseBuilder;

use Exception;
use Jsonpost\utils\ecdsasigner\PowStamp2Message;

/**
 * PoWStampを要求するEndpoint.
 * このエンドポイントはインスタンス化できないぞ。
 */
abstract class AStampRequiredEndpoint
{
    protected const UINT48_MAX=0x0000ffffffffffff;

    public readonly int $accepted_time;
    public readonly PowStamp2 $stamp;
    public readonly PowStamp2Message $stamp_message;
    protected function __construct(PowStamp2 $stamp,PowStamp2Message $stamp_message,?int $accepted_time=null)
    {
        $this->accepted_time=$accepted_time==null? self::getMsNow():$accepted_time;
        $this->stamp=$stamp;
        $this->stamp_message=$stamp_message;
    }    
    // /**
    //  * HttpヘッダからPowStampV1ヘッダを読み出す。成功しない場合は適切な例外を搬出します。
    //  * @throws \Jsonpost\responsebuilder\ErrorResponseBuilder
    //  * @return PowStamp2|null
    //  */
    // protected static function createStamp(?string $chain_hash,?string $rawData):PowStamp2{
    //     //スタンプの評価
    //     $powstamp1 = $_SERVER['HTTP_POWSTAMP_2'] ?? null;
    //     if($powstamp1==null){
    //         ErrorResponseBuilder::throwResponse(201);
    //     }
    //     $ps=null;
    //     try{
    //         $ps=new PowStamp2(hex2bin($powstamp1));
    //     }catch(Exception $e){
    //         ErrorResponseBuilder::throwResponse(201);
    //     }
    //     // if($ps->getNonceAsInt()!=0){
    //     //     ErrorResponseBuilder::throwResponse(204,hint:[]);
    //     // }
    //     $verify_ret=false;
    //     try{
    //         $verify_ret=PowStamp2::verify($ps,PowStamp2Message::createFromStamp($ps,$chain_hash,$rawData));
    //     }catch(Exception $e){
    //         //詳細エラーが取れることある。
    //         ErrorResponseBuilder::throwResponse(203,$e->getMessage());
    //     }
    //     if(!$verify_ret){
    //         ErrorResponseBuilder::throwResponse(203);
    //     }
    //     return $ps;
    // }

    protected static function getMsNow():int{
        return round(microtime(true) * 1000); // 現在の時刻（ミリ秒）
    }
}
