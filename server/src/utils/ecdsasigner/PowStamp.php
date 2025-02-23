<?php
namespace Jsonpost\utils\ecdsasigner;

use Jsonpost\utils\ecdsasigner\EcdsaSignerLite;

class PowStamp {
    /**
     * フィールド名 サイズ(byte) 
     * PowStampSignature 64 SHA256
     * EcdsaPublicKey 33 プレフィクス付キー
     * Nonce 4 メッセージNonce
     * PowNonce 4 ハッシングNonce
     * total 105 
     */
    public readonly string $stamp;

    public function __construct(string $stamp) {
        $this->stamp = $stamp;
    }

    public function getPowNonce(): string {
        return substr($this->stamp, 64+33+4, 4);
    }
    
    public function getPowNonceAsInt(): int {
        return unpack('N', $this->getPowNonce())[1];
    }
    
    public function getNonce(): string {
        return substr($this->stamp, 64+33, 4);
    }
    
    public function getNonceAsInt(): int {
        return unpack('N', $this->getNonce())[1];
    }
    
    public function getPowStampSignature(): string {
        return substr($this->stamp, 0, 64);
    }
    
    public function getEcdsaPubkey(): string {
        return substr($this->stamp, 64, 33);
    }
    
    public function getHash(): string {
        return hash('sha256', hash('sha256', $this->stamp, true), true);
    }
    
    public function getScore(): int {
        $h = $this->getHash();
        $bitCount = 0;
        foreach (str_split($h) as $byte) {
            $value = ord($byte);
            if ($value === 0) {
                $bitCount += 8;
            } else {
                $bitCount += (8 - log($value, 2));
                break;
            }
        }
        return (int)$bitCount;
    }
    
    public static function verify(self $stamp, ?string $serverDomain = null, ?string $payload = null, int $powScore = 0): bool {
        if ($stamp->getScore() < $powScore) {
            return false;
        }
        $psm = PowStampMessage::create(
            $stamp->getEcdsaPubkey(),
            $stamp->getNonce(),
            $serverDomain ? hash('sha256', $serverDomain, true) : null,
            $payload ? hash('sha256', $payload, true) : null
        );
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
