<?php
namespace Jsonpost\utils\ecdsasigner;

use Elliptic\EC;
use \Exception as Exception;






class EasyEcdsaSignature {
    public readonly string $ecdsasignature;
    public readonly string $pubkey;
    public readonly string $data;

    public function __construct(string $ecdsasignature, string $pubkey, string $data) {
        $this->ecdsasignature = $ecdsasignature;
        $this->pubkey = $pubkey;
        $this->data = $data;
    }
    
    public function getSignature(): string {
        return $this->ecdsasignature . $this->pubkey . $this->data;
    }

    public static function fromBytes(string $hex_data): EasyEcdsaSignature {
        if (strlen($hex_data) <= (64 + 33)*2) {
            throw new Exception("EasyEcdsaSignature::Invalid byte length.");
        }
        $t=substr($hex_data,64*2,2);
        $prefix = hexdec($t);//ord($hex_data[64]);
        if (!in_array($prefix, [0x02, 0x03, 0x04], true)) {
            throw new Exception("EasyEcdsaSignature::Invalid public key format.");
        }
        
        $ecdsasignature = substr($hex_data, 0, 64*2);
        $pubkey = substr($hex_data, 64*2, 33*2);
        $data = substr($hex_data, (64 + 33)*2);
        return new EasyEcdsaSignature($ecdsasignature, $pubkey, $data);
    }
}

