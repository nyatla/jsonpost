<?php
// namespace Jsonpost\utils\ecdsasigner;

// use Elliptic\EC;
// use \Exception as Exception;



// /**
//  * Ecdsaの署名ツールのLite版
//  * いくつかのメソッドは実装後回し
//  * 
//  */
// class EcdsaSignerLite {
//     private string $privateKey;
//     private EC $ec;

//     public function __construct(string $privateKey) {
//         $this->privateKey = $privateKey;
//         $this->ec = new EC('secp256k1');
//     }
//     /**
//      * 入力は全てhex文字列であることに注意！
//      * @param string $message
//      * @return string
//      */
//     public function sign(string $message): string {
//         // 秘密鍵からキー・ペアを取得
//         $keyPair = $this->ec->keyFromPrivate($this->privateKey);
        
//         // SHA256 でハッシュを計算（raw バイナリ形式で取得）
//         $msgHash = hash('sha256', $message, false);
        
//         // ハッシュ済みのデータに対して署名を生成
//         // canonical オプションにより、s の値が n/2 以下に正規化される（必要に応じて）
//         $signature = $keyPair->sign(hex2bin($msgHash), ['canonical' => false]);
//         // 署名の r, s を 16 進数文字列に変換し、それぞれ 64 桁にゼロパディング
//         $rHex = str_pad($signature->r->toString(16), 64, "0", STR_PAD_LEFT);
//         $sHex = str_pad($signature->s->toString(16), 64, "0", STR_PAD_LEFT);
//         // r と s を連結し、raw バイナリに変換（Python の sign_digest が返す形式）
//         $rawSignature = $rHex . $sHex;
        
//         return $rawSignature;
//     }

//     /**
//      * プレフィクスありのpublicキーを返す。
//      * プレフィクスは圧縮フラグ。
//      * @return string
//      */
//     public function getPublicKey(bool $compact=false): string
//     {
//         $keyPair = $this->ec->keyFromPrivate($this->privateKey,'hex');
//         $k=$keyPair->getPublic($compact, 'hex');
//         return $k;
//     }
//     private static function compressKey($hexUncompressedPublicKey)
//     {
//         // 16進数文字列をバイナリに変換
//         $uncompressedPublicKey = hex2bin($hexUncompressedPublicKey);
        
//         // 公開鍵の長さが65バイトであることを確認
//         if ($uncompressedPublicKey === false || strlen($uncompressedPublicKey) !== 65 || $uncompressedPublicKey[0] !== "\x04") {
//             throw new Exception('非圧縮形式の公開鍵ではありません。');
//         }
    
//         // x座標（最初の32バイト）を抽出
//         $x = substr($uncompressedPublicKey, 1, 32);
        
//         // y座標の最上位ビットをチェックして、圧縮形式を決定
//         return bin2hex((ord($uncompressedPublicKey[33]) % 2 === 0 ? "\x02" : "\x03") . $x);
//     }

//     /**
//      * 入力は全てhex文字列であることに注意！
//      * @param string $signature
//      * @param string $pubkey
//      * @param string $message
//      * @throws \Exception
//      * @return bool
//      */
//     public static function verify(string $signature,string $pubkey, string $message):bool
//     {
//         $ec = new EC("secp256k1");

//         // メッセージの SHA-256 ハッシュを計算
//         $msgHash = hash('sha256', hex2bin($message), false);
//         // 署名の長さチェック（128文字 = 64バイト）
//         if (strlen($signature) !== 128) {
//             throw new Exception("署名の長さが正しくありません");
//         }
    
//         // `r` と `s` を 16進数からバイナリに変換
//         $r = substr($signature, 0, 64);
//         $s = substr($signature, 64, 64);
    
//         // 公開鍵のオブジェクトを作成
//         $pbk = $ec->keyFromPublic($pubkey, 'hex');
//         $r=$pbk->verify($msgHash, ['r' => $r, 's' => $s]);
//         // `verify()` に署名オブジェクト（連想配列）を渡す
//         return  $r;

//     }

//     /**
//      * プレフィクスありのpublicキーを返す。
//      * プレフィクスは圧縮フラグ。recoveridは0か1
//      * @param string $signature
//      * @param string $message
//      * @return array|array{r: string, s: string}
//      */
//     public static function recover(string $signature, string $message,int $recoverId,bool $compact=false): string
//     {
//         if(strlen($signature)<64*2) {
//             throw new Exception("Signature too short");
//         }
//         if(strlen($message)< 2) {
//             throw new Exception("Message too short");
//         }
//         $ec = new EC('secp256k1');
//         // $data=str_pad($message,64,'0');
//         $msgHash = hash('sha256', $message, false);
//         // print_r("data:$message<br/>");
//         // print_r("dgs:$msgHash<br/>");
    
//         $rHex = substr($signature, 0, 64);
//         $sHex = substr($signature, 64, 64);
//         $signatureData = ['r' => $rHex, 's' => $sHex];    
//         // ECDSA 署名のリカバリー ID は 0 または 1 の 2 通り
//         $publicKeyPoint = $ec->recoverPubKey($msgHash, $signatureData, $recoverId);
//         $k=$publicKeyPoint->encode('hex'); // 公開鍵を 16 進文字列に変換
//         if($compact){
//             $k=EcdsaSignerLite::compressKey($k);
//         }
//         return $k;
//     }

// }




// class EasyEcdsaSignature {
//     public readonly string $ecdsasignature;
//     public readonly string $pubkey;
//     public readonly string $data;

//     public function __construct(string $ecdsasignature, string $pubkey, string $data) {
//         $this->ecdsasignature = $ecdsasignature;
//         $this->pubkey = $pubkey;
//         $this->data = $data;
//     }
    
//     public function getSignature(): string {
//         return $this->ecdsasignature . $this->pubkey . $this->data;
//     }

//     public static function fromBytes(string $d): EasyEcdsaSignature {
//         if (strlen($d) <= 64 + 33) {
//             throw new Exception("Invalid byte length.");
//         }
        
//         $prefix = ord($d[64]);
//         if (!in_array($prefix, [0x02, 0x03, 0x04], true)) {
//             throw new Exception("Invalid public key format.");
//         }
        
//         $ecdsasignature = substr($d, 0, 64);
//         $pubkey = substr($d, 64, 33);
//         $data = substr($d, 64 + 33);
        
//         return new EasyEcdsaSignature($ecdsasignature, $pubkey, $data);
//     }
// }



// class EasyEcdsaStreamBuilderLite{
//     public function __construct(string $pk) {
//         $this->_ecs=new EcdsaSignerLite($pk);
//     }
//     public static function decode(string $signed_sequence): EasyEcdsaSignature {
//         if (strlen($signed_sequence) < (64+35)*2) {
//             throw new Exception("署名シーケンスが短すぎます");
//         }
//         if(preg_match('/^[0-9a-fA-F]+$/', $signed_sequence) !== 1){
//             throw new Exception("署名シーケンスは不正な文字列です。");
//         }
//         $ees=new EasyEcdsaSignature(
//             substr($signed_sequence, 0, 64*2),
//             substr($signed_sequence, 64*2, 33*2),
//             substr($signed_sequence, (64+33)*2)
//         );
//         $r=EcdsaSignerLite::verify(
//             $ees->ecdsasignature,
//             $ees->pubkey,
//             $ees->data);

//         // // シーケンスから署名を除いたデータ部分を取得
//         // $signature = substr($signed_sequence, 0, 64*2);
//         // $pubkey = substr($signed_sequence, 64*2, 33*2);
//         // $payload = substr($signed_sequence, (64+33)*2);
//         if($r === false) {
//             throw new Exception("署名が不正です。");
//         }
            
//         return $ees;
//     }
// }



// class PowEcdsaSignature {
//     public readonly EasyEcdsaSignature $ees;
//     public readonly int $pownonce;

//     public function __construct(EasyEcdsaSignature $ees, int $pownonce) {
//         $this->ees = $ees;
//         $this->pownonce = $pownonce;
//     }

//     public function getSignature(): string {
//         return $this->ees->getSignature() . pack('N', $this->pownonce);
//     }

//     public function getPowBits(): int {
//         return self::countPowBits($this->getSha256d());
//     }

//     public function getSha256d(): string {
//         return hash('sha256', hash('sha256', $this->getSignature(), true), true);
//     }

//     private static function countPowBits(string $data): int {
//         $bitCount = 0;
//         $bytes = unpack('C*', $data); // Convert to array of bytes
        
//         foreach ($bytes as $b) {
//             if ($b === 0) {
//                 $bitCount += 8;
//             } else {
//                 $bitCount += 8 - (int) floor(log($b, 2) + 1);
//                 break;
//             }
//         }
//         return $bitCount;
//     }

//     public static function fromBytes(string $data): PowEcdsaSignature {
//         $ees = EasyEcdsaSignature::fromBytes(substr($data, 0, -4));
//         $pownonce = unpack('N', substr($data, -4))[1];
//         return new PowEcdsaSignature($ees, $pownonce);
//     }
// }

// class PowEcdsaSignatureBuilder {

//     public static function decode($encoded_data = null) {
//         return PowEcdsaSignature::fromBytes($encoded_data);
//     }
// } 