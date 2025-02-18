<?php
namespace Jsonpost\utils\ecdsasigner;

use Elliptic\EC;
use \Exception as Exception;





class PowEcdsaSignature {
    public readonly EasyEcdsaSignature $ees;
    public readonly int $pownonce;

    public function __construct(EasyEcdsaSignature $ees, int $pownonce) {
        $this->ees = $ees;
        $this->pownonce = $pownonce;
        // echo "{$ees->pubkey}\n";
        // echo "$pownonce\n";    
    }

    public function getSignature(): string {
        return $this->ees->getSignature() . bin2hex(pack('N', $this->pownonce));
    }

    public function getPowBits(): int {
        return self::countPowBits($this->getSha256d());
    }

    public function getSha256d(): string {
        return hash('sha256', hash('sha256', hex2bin($this->getSignature()), true), true);
    }

    private static function countPowBits(string $data): int {
        $bitCount = 0;
        $bytes = unpack('C*', $data); // Convert to array of bytes
        
        foreach ($bytes as $b) {
            if ($b === 0) {
                $bitCount += 8;
            } else {
                $bitCount += 8 - (int) floor(log($b, 2) + 1);
                break;
            }
        }
        return $bitCount;
    }

    public static function fromBytes(string $hex_data): PowEcdsaSignature {
        $ees = EasyEcdsaSignature::fromBytes(substr($hex_data, 0, -4*2));
        $pownonce = unpack('N', hex2bin(substr($hex_data, -4*2)))[1];
        // print($ees->getSignature()."</br>");
        // print($pownonce."</br>");
        // print($ees->getSignature()."</br>");
        

        return new PowEcdsaSignature($ees, $pownonce);
    }
}
