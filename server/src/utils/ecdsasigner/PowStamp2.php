<?php
namespace Jsonpost\utils\ecdsasigner;

use Jsonpost\utils\ecdsasigner\EcdsaSignerLite;
use Jsonpost\utils\ecdsasigner\PowStamp2Message;
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
    
    public function recoverMessage(?string $serverDomain = null, ?string $payload = null){
        return PowStamp2Message::create(
            $this->getEcdsaPubkey(),
            $this->getNonce(),
            $serverDomain ? hash('sha256', $serverDomain, true) : null,
            $payload ? hash('sha256', $payload, true) : null         
        );
    }

    
    /**
     * 署名が適切か評価する。
     * @param mixed $serverDomain
     * @param mixed $payload
     * @return bool
     */
    public static function verify(self $stamp, ?string $serverDomain = null, ?string $payload = null): bool {
        $psm = $stamp->recoverMessage($serverDomain,$payload);
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
   
}
