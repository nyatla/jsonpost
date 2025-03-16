<?php
namespace Jsonpost\endpoint;



use Jsonpost\utils\ecdsasigner\PowStamp2;


/**
 * chain_hash/rawDataによらずverify済のエンドポイント。
 */
class VerifiedStampEndpoint extends AStampRequiredEndpoint
{
    protected function __construct(PowStamp2 $stamp){
        parent::__construct(stamp: $stamp);
    }

    /**
     * chain_hashとrawDataを省略した場合はゼロフィルと仮定します。
     * @param mixed $chain_hash
     * @param mixed $rawData
     * @return VerifiedStampEndpoint
     */
    public static function create(?string $chain_hash=null,?string $rawData=null): VerifiedStampEndpoint{
        return new VerifiedStampEndpoint(
            PowStamp2::createVerifiedFromHeader($chain_hash,$rawData));
    }
}