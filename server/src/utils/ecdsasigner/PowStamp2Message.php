<?php
namespace Jsonpost\utils\ecdsasigner;

use Jsonpost\utils\ecdsasigner\EasyEcdsaSignature;
use Exception;
use Jsonpost\responsebuilder\{ErrorResponseBuilder};

class PowStamp2Message {
    /**
     * EcdsaPublicKey	33	プレフィクス付キー
     * Nonce	6	メッセージNonce
     * ServerDomainHash(sha256)	32	
     * PayloadHash(sha256)	32	
     * total	103
     */
    public readonly string $message;

    public function __construct(string $message) {
        $this->message = $message;
    }

    public function getEcdsaPubkey(): string {
        return substr($this->message, 0, 33);
    }

    public function getNonce(): string {
        return substr($this->message, 33, 6);
    }
    public function getNonceAsU48(): int {
        return unpack('J', "\x00\x00" . $this->getNonce())[1];
    }
    public function getServerDomainHash(): string {
        return substr($this->message, 39, 32);
    }

    public function getPayloadHash(): string {
        return substr($this->message, 71, 32);
    }
    public function getHash(): string {
        return hash('sha256', hash('sha256', $this->message, true), true);
    }
    public function getPowScoreU48(): int {
        $hash = $this->getHash();
        // 6バイトに2バイトのゼロパディングを追加して8バイトにする
        $padded = "\x00\x00" . substr($hash, 0, 6);
        return unpack('J', $padded)[1];        
    }
    public static function create(string $pubkey, string $nonce, ?string $chain_hash=null, ?string $payloadHash = null): self {
        
        if (strlen($pubkey) !== 33) {
            throw new Exception("Invalid pubkey length");
        }
        if (strlen($nonce) !== 6) {
            throw new Exception("Invalid nonce length");
        }
        
        $b = $pubkey . $nonce;
        $b .= $chain_hash? $chain_hash : str_repeat("\x00", times: 32);
        $b .= $payloadHash ? $payloadHash : str_repeat("\x00", times: 32);
        
        return new self($b);
    }

    

}