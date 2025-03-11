# PowStampV2仕様

この仕様は[PowStamp](./powstamp.md)の利用実績を踏まえた改良版です。
データ形式のうち、PowStampとPowStampMessageの両方を変更しています。

PowStampV2では、V1のPowNonceとNonceを統合します。

以下の項目を変更しました。

- PowStampMessageのPowNonceをNonceに変更し、バイト長を8に拡大
- PowStampから.PowNonceを削除し、Nonceのバイト長を8に拡大
- PowStampのスコア値算定方式を変更。
- これに伴いハッシング対象をPowStampからPowStampMessageに変更


これらにより、データ形式の単純化、処理方式の明確化、対応ハッシュレートの拡大などの効果を見込みます。





## データ形式

PowStampには、認証情報としての `PowStamp` と、署名情報を作成するための `PowStampMessage` の2種類のデータ構造があります。

フィールドは全てバイト値です。

### PowStamp

`PowStamp` は、以下の3つの要素で構成されます。

- **ECDSA署名 (`PowStampSignature`)**
- **ECDSA署名を検証するためのメッセージ (`PowStampMessage`)**
- **PoWハッシングで得る `PowNonce`**

クライアントは、初回の通信時に永続的に有効なECDSAのプライベートキーを生成し、これを用いて署名を行います。

| フィールド名            | サイズ(byte) | 説明                                  |
|------------------|------------|---------------------------------|
| PowStampSignature | 64         | `ECDSA.sign(PowStampMessage)` の結果 |
| EcdsaPublicKey   | 33         | 圧縮形式のECDSA公開鍵                  |
| Nonce            | 6          | Nonce(BigEndian)                      |
| **total**        | **103**    | 合計バイト数                            |


ECDSA署名のK値は固定するべきではありません。これはPoWStampが同一な署名の使用制限しないためです。

### PowStampMessage

`PowStampMessage` は `PowStampSignature` の署名対象となるメッセージです。
このメッセージは、サーバー由来の情報とクライアント由来の情報を一定の方式で連結して作成します。

| フィールド名            | サイズ(byte) | 説明                                  |
|------------------|------------|---------------------------------|
| EcdsaPublicKey   | 33         | プレフィクス付きECDSA公開鍵                |
| Nonce            | 6          | メッセージNonce(BigEndian)                 |
| ServerDomainHash | 32         | サーバー名のSHA256ハッシュ               |
| PayloadHash      | 32         | 送信ペイロードのSHA256ハッシュ            |
| **total**        | **103**    | 合計バイト数                            |

- EcdsaPublicKey  
    クライアントが所有するECDSA秘密鍵から生成します。
- ServerDomainHash  
    サーバーから提供されるドメイン名のSHA256ハッシュです。
    Publicサーバーの場合は `sha256(0[32])` を指定します。
- PayloadHash  
    クライアントが準備する情報で、PowStampと共に送信されるペイロードのSHA256ハッシュ値です。
    ペイロードを持たない場合は `sha256(0[32])` を指定します。
- Nonce  
    Nonceはサーバにより受け入れ下限が提示され、クライアントによりその範囲から選択される値です。
    初期値は0であり、クライアントは常にサーバの提示する値よりも大きい値を選択しなければなりません。  
    受け入れ下限の最大値は0x0000ffffffffffffです。これ以上の値は存在しないため、サーバの提示値がこの値に到達した時点で、そのPowStampは生成不能になります。


## PowStampのスコア

`sha256d(PowStampMessage)` の結果の先頭48ビットを `big-endian UINT` として解釈した値がスコア値です。
サーバーはこの値を閾値と比較し、閾値未満であれば有効なPowStampとして受理します。

スコア値は、値が低いほど出現率が低くなる性質があります。
サーバーはこの値に閾値を設定し、PosStampに演算コストを要求します。

### 閾値

サーバが使用するPowStampスコアの閾値m(m>0)は、[0,0x0000ffffffffffff]の範囲にある値です。
閾値はPowStampのスコアの有効/無効を決定するための値で、閾値未満の値を有効とすることで、

(m-1)/(0x0000ffffffffffff)の確率で有効なPowStampを得ることができるようになります。

### スコアのビジュアライズ

スコアと閾値は値空間に対して指数関数的な分布の確率値です。そのため、ビジュアライズは対数表記で行うべきです。
例えばスコアを[0,1]の範囲で表示する場合は次の計算式が有効です。
```
rate=log2(score)/32
```


### ハッシング

目的のスコアを持つPowStampを得るには、PowStampのPowNonceフィールドの値を変更しながらsha256dの結果を評価するプロセスを繰り返します。目標をtarget_scoreとすると、以下の疑似コードで表現されます。これは確率的な探索になります。

hash_baseはPowStampのPowNonceを除く101バイトです。

<!-- ```
for i in range(0x0000ffffffffffff):
    pw=sha256(sha256(hash_base+struct.pack('>Q', i)))
    if unpack('>I', pw[0:4])[0]>target_score:
        return pw
``` -->







## 通信方式

現在のバージョンでは、HTTPリクエストヘッダー `PowStamp-2` に `hex` 形式のPowStampを指定します。

**リクエストの例**
<!-- ```
GET /status.php HTTP/1.1
User-Agent: python-requests/2.31.0
Accept-Encoding: gzip, deflate, br
Accept: */*
Connection: keep-alive
PowStamp-2: 1afe01c478b26b091e28568e921ba72fdfd253f0400deed94f482e9825113071034f8d917a1b18c2905dc68ad093188af3da4814f18998a751b0e291b38d4cb702cf751b15ce7de09d29aa612a48788b7ce576ba513a50c666404131d2988f57180000012b00000001
``` -->




## 参考

PowStampとPowStampMessageの生成例です。

k値がランダムの為、署名の値は生成毎に異なります。

<!-- ```
import os,sys
import hashlib
import struct
from libs.powstamp import PowStamp,PowStampMessage
from libs.ecdsa_utils import EcdsaSignner
pk = b'0'*32
nonce = 1
server_domain = "TEST"
payload = b"TEST"

es=EcdsaSignner(pk)
spubkey=EcdsaSignner.compressPubKey(es.public_key)
sdhash=None if server_domain is None else hashlib.sha256(server_domain.encode()).digest()
phash=None if payload is None else hashlib.sha256(payload).digest()

sm=PowStampMessage.create(
    spubkey,
    struct.pack('>I', nonce),
    sdhash,
    phash,
)
hash_base=es.sign(sm.message)+spubkey+struct.pack('>I',nonce)
pw=PowStamp(hash_base+struct.pack('>I', 0))


print("**PowStampMessage**")

print("EcdsaPublicKey",spubkey.hex())
print("Nonce",sm.nonce.hex())
print("ServerDomainHash",sdhash.hex())
print("PayloadHash",phash.hex())
print("Total",sm.message.hex())

print("**PowStamp**")
print("PowStampSignature",pw.powStampSignature.hex())
print("EcdsaPublicKey",pw.ecdsaPubkey.hex())
print("Nonce",pw.nonce.hex())
print("PowNonce",pw.powNonce.hex())
print("Total",pw.stamp.hex())
```

```
**PowStampMessage**
EcdsaPublicKey 022ed557f5ad336b31a49857e4e9664954ac33385aa20a93e2d64bfe7f08f51277
Nonce 00000001
ServerDomainHash 94ee059335e587e501cc4bf90613e0814f00a7b08bc7c648fd865a2af6a22cc2
PayloadHash 94ee059335e587e501cc4bf90613e0814f00a7b08bc7c648fd865a2af6a22cc2
Total 022ed557f5ad336b31a49857e4e9664954ac33385aa20a93e2d64bfe7f08f512770000000194ee059335e587e501cc4bf90613e0814f00a7b08bc7c648fd865a2af6a22cc294ee059335e587e501cc4bf90613e0814f00a7b08bc7c648fd865a2af6a22cc2
**PowStamp**
PowStampSignature 83c9175403510b8fc7c25ba6f66b42b8e50a17d87f4824660f96ffa9f3bf99f92cd513f7b2cb88e527be81da21f11ce29f8c43d4a1568133984f520c0e4ad74e
EcdsaPublicKey 022ed557f5ad336b31a49857e4e9664954ac33385aa20a93e2d64bfe7f08f51277
Nonce 00000001
PowNonce 00000000
Total 83c9175403510b8fc7c25ba6f66b42b8e50a17d87f4824660f96ffa9f3bf99f92cd513f7b2cb88e527be81da21f11ce29f8c43d4a1568133984f520c0e4ad74e022ed557f5ad336b31a49857e4e9664954ac33385aa20a93e2d64bfe7f08f512770000000100000000

``` -->


