<?php
namespace Jsonpost\utils\ecdsasigner;

use Jsonpost\utils\ecdsasigner\EasyEcdsaSignature;
use \Exception as Exception;

class PowStampMessage {
    /**
     * EcdsaPublicKey	33	プレフィクス付キー
     * Nonce	4	メッセージNonce
     * ServerDomainHash(sha256)	32	
     * PayloadHash(sha256)	32	
     * total	165
     */
    public readonly string $message;

    public function __construct(string $message) {
        $this->message = $message;
    }

    public function getEcdsaPubkey(): string {
        return substr($this->message, 0, 33);
    }

    public function getNonce(): string {
        return substr($this->message, 33, 4);
    }

    public function getServerDomainHash(): string {
        return substr($this->message, 37, 32);
    }

    public function getPayloadHash(): string {
        return substr($this->message, 69, 32);
    }

    public static function create(string $pubkey, string $nonce, ?string $serverDomainHash = null, ?string $payloadHash = null): self {
        
        if (strlen($pubkey) !== 33) {
            throw new Exception("Invalid pubkey length");
        }
        if (strlen($nonce) !== 4) {
            throw new Exception("Invalid nonce length");
        }
        
        $b = $pubkey . $nonce;
        $b .= $serverDomainHash ? $serverDomainHash : str_repeat("\x00", 32);
        $b .= $payloadHash ? $payloadHash : str_repeat("\x00", times: 32);
        
        return new self($b);
    }
}