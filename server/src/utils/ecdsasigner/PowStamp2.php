<?php
namespace Jsonpost\utils\ecdsasigner;

use Jsonpost\responsebuilder\{ErrorResponseBuilder};
use Jsonpost\utils\ecdsasigner\EcdsaSignerLite;
use Jsonpost\utils\ecdsasigner\PowStamp2Message;


use \Exception as Exception;

class PowStamp2 {
    /**
     * フィールド名 サイズ(byte) 
     * PowStampSignature 64 SHA256
     * EcdsaPublicKey 33 プレフィクス付キー
     * Nonce 6 メッセージNonce
     * total 103 
     */
    public readonly string $stamp;

    public function __construct(string $stamp) {
        assert(strlen($stamp) === 103);
        $this->stamp = $stamp;
    }
    
    public function getNonce(): string {
        return substr($this->stamp, 64+33, 6);
    }
    
    public function getNonceAsU48(): int {
        return unpack('J', "\x00\x00" . $this->getNonce())[1];
    }
    
    public function getPowStampSignature(): string {
        return substr($this->stamp, 0, 64);
    }
    
    public function getEcdsaPubkey(): string {
        return substr($this->stamp, 64, 33);
    }
    
    public function recoverMessage(?string $chain_hash, ?string $payload = null){
        return PowStamp2Message::create(
            $this->getEcdsaPubkey(),
            $this->getNonce(),
            $chain_hash    ,
            $payload ? hash('sha256', $payload, true) : null         
        );
    }
    public function getHash(): string {
        return hash('sha256', $this->stamp, true);
    }
    
    /**
     * 署名が適切か評価する。
     * @param mixed $serverDomain
     * @param mixed $payload
     * @return bool
     */
    public static function verify(self $stamp, ?string $chain_hash, ?string $payload = null): bool {
        $psm = $stamp->recoverMessage($chain_hash,$payload);
        // print_r(bin2hex($stamp->stamp)."\n");        
        // print_r(bin2hex($stamp->getPowStampSignature())."\n");        
        // print_r(bin2hex($psm->getEcdsaPubkey())."\n");        
        // print_r(bin2hex($psm->message)."\n");        
        // print_r(bin2hex($psm->getServerDomainHash())."\n");        
        // print_r(bin2hex($psm->getPayloadHash())."\n");        
        // print_r("-----------");
        return EcdsaSignerLite::verify(
            bin2hex($stamp->getPowStampSignature()),
            bin2hex($psm->getEcdsaPubkey()),
            $psm->message
        );
    }
    public static function createFromHeader():PowStamp2
    {
        $powstamp1 = $_SERVER['HTTP_POWSTAMP_2'] ?? null;
        if($powstamp1==null){
            ErrorResponseBuilder::throwResponse(201);
        }
        $ret=null;
        try{
            $ret=new PowStamp2(hex2bin($powstamp1));
        }catch(Exception $e){
            ErrorResponseBuilder::throwResponse(201);
        }    
        return $ret;
    }
    public static function createVerifiedFromHeader(?string $chain_hash,?string $rawData):PowStamp2{
        $stamp=self::createFromHeader();
        $verify_ret=false;
        try{
            $verify_ret=PowStamp2::verify($stamp,$chain_hash,$rawData);
        }catch(Exception $e){
            //詳細エラーが取れることある。
            ErrorResponseBuilder::throwResponse(203,$e->getMessage());
        }
        if(!$verify_ret){
            ErrorResponseBuilder::throwResponse(203);
        }
        return $stamp;
    }
}
