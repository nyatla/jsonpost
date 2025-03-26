<?php
namespace Jsonpost\endpoint;



use Jsonpost\utils\ecdsasigner\PowStamp2;
use Jsonpost\utils\ecdsasigner\PowStamp2Message;
use Jsonpost\responsebuilder\ErrorResponseBuilder;


/**
 * chain_hash/rawDataによらずverify済のエンドポイント。
 */
class VerifiedStampEndpoint extends AStampRequiredEndpoint
{
    protected function __construct(PowStamp2 $stamp,PowStamp2Message $stamp_message){
        parent::__construct($stamp,$stamp_message);
    }

    /**
     * chain_hashとrawDataを省略した場合はゼロフィルと仮定します。
     * @param mixed $chain_hash
     * @param mixed $rawData
     * @return VerifiedStampEndpoint
     */
    public static function create(?string $chain_hash=null,?string $rawData=null): VerifiedStampEndpoint{
        $ps=PowStamp2::createFromHeader();
        $psm=$ps->recoverMessage($chain_hash,$rawData);
        if(!$ps->verify($psm)){
            ErrorResponseBuilder::throwResponse(203);
        }
        return new VerifiedStampEndpoint($ps,$psm);
    }
}