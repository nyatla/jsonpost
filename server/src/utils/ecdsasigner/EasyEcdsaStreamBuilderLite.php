<?php
namespace Jsonpost\utils\ecdsasigner;

use Elliptic\EC;
use \Exception as Exception;




class EasyEcdsaStreamBuilderLite{
    public function __construct(string $pk) {
        $this->_ecs=new EcdsaSignerLite($pk);
    }
    public static function decode(string $signed_sequence): EasyEcdsaSignature {
        if (strlen($signed_sequence) < (64+35)*2) {
            throw new Exception("署名シーケンスが短すぎます");
        }
        if(preg_match('/^[0-9a-fA-F]+$/', $signed_sequence) !== 1){
            throw new Exception("署名シーケンスは不正な文字列です。");
        }
        $b=$signed_sequence;
        $ees=new EasyEcdsaSignature(
            substr($b, 0, 64*2),
            substr($b, 64*2, 33*2),
            substr($b, (64+33)*2)
        );

        $r=EcdsaSignerLite::verify(
            $ees->ecdsasignature,
            $ees->pubkey,
            hex2bin($ees->data));

        // // シーケンスから署名を除いたデータ部分を取得
        // $signature = substr($signed_sequence, 0, 64*2);
        // $pubkey = substr($signed_sequence, 64*2, 33*2);
        // $payload = substr($signed_sequence, (64+33)*2);
        if($r === false) {
            throw new Exception("Invalid EasyEcdsaSignature");
        }
            
        return $ees;
    }
}

