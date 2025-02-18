<?php
namespace Jsonpost\utils\ecdsasigner;






class PowEcdsaSignatureBuilder {

    public static function decode($encoded_hex_data = null) {
        return PowEcdsaSignature::fromBytes($encoded_hex_data);
    }
}