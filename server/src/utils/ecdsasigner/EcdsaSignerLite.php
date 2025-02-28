<?php
namespace Jsonpost\utils\ecdsasigner;

use Elliptic\EC;
use Exception;



/**
 * Ecdsaの署名ツールのLite版
 * いくつかのメソッドは実装後回し
 * 
 */
class EcdsaSignerLite {
    private string $privateKey;
    private EC $ec;

    /**
     * @param string $privateKey
     * HEX
     */
    public function __construct(string $privateKey) {
        $this->privateKey = $privateKey;
        $this->ec = new EC('secp256k1');
    }
    /**
     * $messageは普通のメッセージ
     * @param string $message
     * 通常文字
     * @return string
     */
    public function sign(string $message): string
    {
        //Kを固定してないからね
        // 秘密鍵からキー・ペアを取得
        $keyPair = $this->ec->keyFromPrivate($this->privateKey,'hex');
        
        // SHA256 でハッシュを計算（raw バイナリ形式で取得）
        $msgHash = hash('sha256', $message, false);
        // print("she256:$msgHash<br/>");
        
        // ハッシュ済みのデータに対して署名を生成
        // canonical オプションにより、s の値が n/2 以下に正規化される（必要に応じて）
        $signature = $keyPair->sign($msgHash, ['canonical' => false]);
        // 署名の r, s を 16 進数文字列に変換し、それぞれ 64 桁にゼロパディング
        $rHex = str_pad($signature->r->toString(16), 64, "0", STR_PAD_LEFT);
        $sHex = str_pad($signature->s->toString(16), 64, "0", STR_PAD_LEFT);
        // r と s を連結し、raw バイナリに変換（Python の sign_digest が返す形式）
        $rawSignature = $rHex . $sHex;
        return $rawSignature;
    }

    /**
     * プレフィクスありのpublicキーを返す。
     * プレフィクスは圧縮フラグ。
     * @return string
     */
    public function getPublicKey(bool $compact=false): string
    {
        $keyPair = $this->ec->keyFromPrivate($this->privateKey,'hex');
        $k=$keyPair->getPublic($compact, 'hex');
        return $k;
    }
    /**
     * @param mixed $hexUncompressedPublicKey
     * HEX
     * @throws \Exception
     * @return string
     */
    public static function compressKey($hexUncompressedPublicKey)
    {
        // // 16進数文字列をバイナリに変換
        $uncompressedPublicKey = hex2bin($hexUncompressedPublicKey);
        
        // 公開鍵の長さが65バイトであることを確認
        if ($uncompressedPublicKey === false || strlen($uncompressedPublicKey) !== 65 || $uncompressedPublicKey[0] !== "\x04") {
            throw new Exception('非圧縮形式の公開鍵ではありません。');
        }
    
        // x座標（最初の32バイト）を抽出
        $x = substr($uncompressedPublicKey, 1, 32);
        
        // y座標の最上位ビットをチェックして、圧縮形式を決定
        return bin2hex((ord($uncompressedPublicKey[33]) % 2 === 0 ? "\x02" : "\x03" ). $x);
    }

    /**
     * 
     * @param string $signature
     * HEX入力
     * @param string $pubkey
     * HEX入力
     * @param string $message
     * 文字入力
     * @throws \Exception
     * @return bool
     */
    public static function verify(string $signature,string $pubkey, string $message):bool
    {
        $ec = new EC("secp256k1");

        // メッセージの SHA-256 ハッシュを計算
        $msgHash = hash('sha256', $message, false);
        // 署名の長さチェック（128文字 = 64バイト）
        if (strlen($signature) !== 64*2) {
            throw new Exception("EcdsaSignerLite::Invalid ecdsa signature length.");
        }
    
        // `r` と `s` を 16進数からバイナリに変換
        $r = substr($signature, 0, 32*2);
        $s = substr($signature, 32*2, 32*2);
    
        // 公開鍵のオブジェクトを作成
        $pbk = $ec->keyFromPublic($pubkey, 'hex');
        $r=$pbk->verify($msgHash, ['r' => $r, 's' => $s]);
        // `verify()` に署名オブジェクト（連想配列）を渡す
        return  $r;

    }

    /**
     * プレフィクスありのpublicキーを返す。
     * プレフィクスは圧縮フラグ。recoveridは0か1
     * @param string $signature
     * @param string $message
     * @return array|array{r: string, s: string}
     */
    public static function recover(string $signature, string $message,int $recoverId,bool $compact=false): string
    {
        if(strlen($signature)<64*2) {
            throw new Exception("EcdsaSignerLite::Signature too short");
        }
        if(strlen($message)< 2) {
            throw new Exception("EcdsaSignerLite::Message too short");
        }
        $ec = new EC('secp256k1');
        // $data=str_pad($message,64,'0');
        $msgHash = hash('sha256', $message, false);
        // print_r("data:$message<br/>");
        // print_r("dgs:$msgHash<br/>");
    
        $rHex = substr($signature, 0, 64);
        $sHex = substr($signature, 64, 64);
        $signatureData = ['r' => $rHex, 's' => $sHex];    
        // ECDSA 署名のリカバリー ID は 0 または 1 の 2 通り
        $publicKeyPoint = $ec->recoverPubKey($msgHash, $signatureData, $recoverId);
        $k=$publicKeyPoint->encode('hex'); // 公開鍵を 16 進文字列に変換
        if($compact){
            $k=EcdsaSignerLite::compressKey($k);
        }
        return $k;
    }

}

