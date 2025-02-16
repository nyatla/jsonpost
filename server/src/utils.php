<?php
namespace Jsonpost{

    require dirname(__FILE__) .'/../vendor/autoload.php'; // Composerでインストールしたライブラリを読み込む

    use Ramsey\Uuid\Uuid;
    use Elliptic\EC;
    use \Exception as Exception;


    /**
     * Ecdsaの署名ツールのLite版
     * いくつかのメソッドは実装後回し
     * 
     */
    class EcdsaSignerLite {
        private string $privateKey;
        private EC $ec;

        public function __construct(string $privateKey) {
            $this->privateKey = $privateKey;
            $this->ec = new EC('secp256k1');
        }
        /**
         * 入力は全てhex文字列であることに注意！
         * @param string $message
         * @return string
         */
        public function sign(string $message): string {
            // 秘密鍵からキー・ペアを取得
            $keyPair = $this->ec->keyFromPrivate($this->privateKey);
            
            // SHA256 でハッシュを計算（raw バイナリ形式で取得）
            $msgHash = hash('sha256', $message, false);
            
            // ハッシュ済みのデータに対して署名を生成
            // canonical オプションにより、s の値が n/2 以下に正規化される（必要に応じて）
            $signature = $keyPair->sign(hex2bin($msgHash), ['canonical' => false]);
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
        private static function compressKey($hexUncompressedPublicKey)
        {
            // 16進数文字列をバイナリに変換
            $uncompressedPublicKey = hex2bin($hexUncompressedPublicKey);
            
            // 公開鍵の長さが65バイトであることを確認
            if ($uncompressedPublicKey === false || strlen($uncompressedPublicKey) !== 65 || $uncompressedPublicKey[0] !== "\x04") {
                throw new Exception('非圧縮形式の公開鍵ではありません。');
            }
        
            // x座標（最初の32バイト）を抽出
            $x = substr($uncompressedPublicKey, 1, 32);
            
            // y座標の最上位ビットをチェックして、圧縮形式を決定
            return bin2hex((ord($uncompressedPublicKey[33]) % 2 === 0 ? "\x02" : "\x03") . $x);
        }

        /**
         * 入力は全てhex文字列であることに注意！
         * @param string $signature
         * @param string $pubkey
         * @param string $message
         * @throws \Exception
         * @return bool
         */
        public static function verify(string $signature,string $pubkey, string $message):bool
        {
            $ec = new EC("secp256k1");

            // メッセージの SHA-256 ハッシュを計算
            $msgHash = hash('sha256', hex2bin($message), false);
            // 署名の長さチェック（128文字 = 64バイト）
            if (strlen($signature) !== 128) {
                throw new Exception("署名の長さが正しくありません");
            }
        
            // `r` と `s` を 16進数からバイナリに変換
            $r = substr($signature, 0, 64);
            $s = substr($signature, 64, 64);
        
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
                throw new Exception("Signature too short");
            }
            if(strlen($message)< 2) {
                throw new Exception("Message too short");
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


    class EasyEcdsaStreamBuilderLite{
        public function __construct(string $pk) {
            $this->_ecs=new EcdsaSignerLite($pk);
        }
        // function encode(string $data){
        //     $ecs=$this->_ecs;
        //     $signature = $ecs->sign($data);
        //     $rid=$ecs.detectRecoverId($signature,$data)
        //     return $signature.$rid.to_bytes(1, byteorder='big')+data
        // }
        public static function decode(string $signed_sequence): array/*<string> */ {
            if (strlen($signed_sequence) < (64+35)*2) {
                throw new Exception("署名シーケンスが短すぎます");
            }
            if(preg_match('/^[0-9a-fA-F]+$/', $signed_sequence) !== 1){
                throw new Exception("署名シーケンスは不正な文字列です。");
            }
            // シーケンスから署名を除いたデータ部分を取得
            $signature = substr($signed_sequence, 0, 64*2);
            $pubkey = substr($signed_sequence, 64*2, 33*2);
            $payload = substr($signed_sequence, (64+33)*2);
            $r=EcdsaSignerLite::verify($signature,$pubkey,$payload);
            if($r === false) {
                throw new Exception("署名が不正です。");
            }
                
            return [$pubkey,$payload];
        }    
    }
















    class UUIDGenerator
    {
        /**
         * UUIDv1（タイムスタンプ+MACアドレス）を生成
         * @return string UUIDv1
         */
        public static function generateV1()
        {
            return Uuid::uuid1()->toString();
        }

        /**
         * UUIDv5（名前空間 + 名前）を生成
         * @param string $namespace UUIDの名前空間（UUID形式）
         * @param string $name ユニークな名前
         * @return string UUIDv5
         */
        public static function generateV5_sha256_url($name)
        {
            try {
                return Uuid::uuid5(Uuid::NAMESPACE_URL, $name)->toString();
            } catch (Exception $e) {
                die("UUIDv5の生成に失敗しました: " . $e->getMessage());
            }
        }
    }
}

?>