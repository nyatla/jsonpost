<?php
namespace Jsonpost\endpoint;

use Jsonpost\utils\ecdsasigner\PowStamp2;
use Jsonpost\responsebuilder\ErrorResponseBuilder;

use Exception;

/**
 * PoWStampを要求するEndpoint.
 * このエンドポイントはインスタンス化できないぞ。
 */
abstract class StampRequiredEndpoint
{
    public readonly int $accepted_time;
    public readonly PowStamp2 $stamp;
    protected function __construct(int $accepted_time,PowStamp2 $stamp)
    {
        $this->accepted_time=$accepted_time;
        $this->stamp=$stamp;
    }    
    /**
     * HttpヘッダからPowStampV1ヘッダを読み出す。成功しない場合は適切な例外を搬出します。
     * @throws \Jsonpost\responsebuilder\ErrorResponseBuilder
     * @return PowStamp2|null
     */
    protected static function createStamp(?string $server_name,?string $rawData):PowStamp2{
        //スタンプの評価
        $powstamp1 = $_SERVER['HTTP_POWSTAMP_2'] ?? null;
        if($powstamp1==null){
            ErrorResponseBuilder::throwResponse(201);
        }
        $ps=null;
        try{
            $ps=new PowStamp2(hex2bin($powstamp1));
        }catch(Exception $e){
            ErrorResponseBuilder::throwResponse(201);
        }
        // if($ps->getNonceAsInt()!=0){
        //     ErrorResponseBuilder::throwResponse(204,hint:[]);
        // }
        $verify_ret=false;
        try{
            $verify_ret=PowStamp2::verify($ps,$server_name,$rawData);
        }catch(Exception $e){
            //詳細エラーが取れることある。
            ErrorResponseBuilder::throwResponse(203,$e->getMessage());
        }
        if(!$verify_ret){
            ErrorResponseBuilder::throwResponse(203);
        }
        return $ps;
    }
    // public static function create(?string $server_name,?string $rawData):RawStampRequiredEndpoint{
    //     return new RawStampRequiredEndpoint(
    //         self::getMsNow(),
    //         self::createStamp($server_name,$rawData));
    // }

    protected static function getMsNow():int{
        return round(microtime(true) * 1000); // 現在の時刻（ミリ秒）
    }
}
