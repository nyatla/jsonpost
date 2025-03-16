# セキュリティの考え方

JsonPostでは、PoWとNonceによる確率的なアクセス制御を実装しています。
断定式のアクセス制御にしない理由は、他の攻撃方法よりも低コストな攻撃方法を許容することで、攻撃者をコントロール可能な攻撃に誘導するためです。


PowStampの考え方

PosStampは、外見としてはトランザクションのハッシュです。
このハッシュは、元となるペイロードの内容、証明したアカウントの存在、費やしたコストを証明します。


アカウントの存在

PowStampにはNonceが含まれます。Nonceはスタンプをサーバーが受理する度に増加しなければならない変数です。
これにより、アカウントは寿命を持つ存在になります。

PowStampの製造コスト

PowStampはsha2d(PowStamp)

PowStampはスコアを持ちます。このスコアはハッシングにより生成する予測不能な確率値で、平均すると










アカウントはPowStampを作成するためにNonceを消費しなければなりません。




imestamp



PowStampは、クライアントがサーバーに送信するバイトストリームです。
このデータは、クライアントがリクエストの正当性を証明するために作成し、送信します。
基本的には1回限りの使い捨て情報であり、APIが受理されるたびに新しく生成する必要があります。

PowStampの `sha256d` 値は、PowStampのスコア値として定義されます。
この値の先頭32ビットがPoWの評価対象となり、サーバーの要求する目標値に適合させる必要があります。

## データ形式

PowStampには、認証情報としての `PowStamp` と、署名情報を作成するための `PowStampMessage` の2種類のデータ構造があります。

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
| Nonce            | 4          | メッセージNonce                        |
| PowNonce         | 4          | ハッシング調整用Nonce                  |
| **total**        | **105**    | 合計バイト数                            |


ECDSA署名のK値は固定するべきではありません。これはPoWStampが同一な署名の使用制限しないためです。

### PowStampMessage

`PowStampMessage` は `PowStampSignature` の署名対象となるメッセージです。
このメッセージは、サーバー由来の情報とクライアント由来の情報を一定の方式で連結して作成します。

| フィールド名            | サイズ(byte) | 説明                                  |
|------------------|------------|---------------------------------|
| EcdsaPublicKey   | 33         | プレフィクス付きECDSA公開鍵                |
| Nonce            | 4          | メッセージNonce                        |
| ServerDomainHash | 32         | サーバー名のSHA256ハッシュ               |
| PayloadHash      | 32         | 送信ペイロードのSHA256ハッシュ            |
| **total**        | **101**    | 合計バイト数                            |

ServerDomainHash は、サーバーから提供されるドメイン名のSHA256ハッシュです。
Publicサーバーの場合は `sha256(0[32])` を指定します。
PayloadHash は、PowStampと共に送信されるペイロードのSHA256ハッシュ値です。
ペイロードを持たない場合は `sha256(0[32])` を指定します。

## PowStampのスコア

`sha256d(PowStamp)` の結果の先頭32ビットを `big-endian UINT` として解釈した値がスコア値です。
サーバーはこの値を閾値と比較し、閾値以下であれば有効なPowStampとして受理します。

閾値は動的に変更可能であり、サーバーから提供されるAPIで取得できます。

### 難易度計算

PowStampのスコアは閾値として使用されますが、ハッシングの難易度として直感的に理解しにくいため、以下の計算式で[0,32]の範囲に変換できます。

```
DIFF = (0xffffffff - SCORE_TH)
```

また、先頭から揃えるべき0のビット数は以下の式で求めます。

```
DIFF_BITS = floor(log2(0xffffffff - SCORE_TH))
```

### 閾値計算アルゴリズム

サーバーのPowStampスコアの閾値は、リクエストごとに計算される適正度 `R` を基に決定されます。

```
難易度閾値 = pow(2, (R * 32))
```

#### TimeLogiticsSizeLogNormal

汎用的なファイルアップロードで適正な利用頻度と容量を求める難易度設定です。
ファイルサイズはリクエストから、経過時間はサーバの記録値から求めます。

この方式では、アップロードファイルサイズ の適正率(`PSR`) とアップロード間隔適正率 (`ETR`) の2つのパラメータで決定します。

- `PSR`: ファイルサイズを目標ファイルサイズと対数正規分布に基づいて[0,1]に正規化した値
- `ETR`: アップロード間隔（時間）をロジスティック関数で[0,1]に変換した値
- `適正度 R = PSR × ETR`


##### シリアライズ

APIのパラメータの表記方法は以下の通りです。
```
tlsln(e,s,s_sigma)
```
- **e** 秒単位のアップロード間隔目標値です。
- **s** キロバイト単位のアップロードサイズ目標値です。
- **s_sigma** アップロードサイズの対数正規分布のσ値です。


## ハッシング

目的のスコアを持つPowStampを得るには、PowStampのPowNonceフィールドの値を変更しながらsha256dの結果を評価するプロセスを繰り返します。目標をtarget_scoreとすると、以下の疑似コードで表現されます。これは確率的な探索になります。

hash_baseはPowStampのPowNonceを除く101バイトです。

```
for i in range(0xffffffff):
    pw=sha256(sha256(hash_base+struct.pack('>I', i)))
    if unpack('>I', pw[0:4])[0]>target_score:
        return pw
```

## 通知方式

現在のバージョンでは、HTTPリクエストヘッダー `PowStamp-1` に `hex` 形式のPowStampを指定します。

**リクエストの例**
```
GET /status.php HTTP/1.1
User-Agent: python-requests/2.31.0
Accept-Encoding: gzip, deflate, br
Accept: */*
Connection: keep-alive
PowStamp-1: 1afe01c478b26b091e28568e921ba72fdfd253f0400deed94f482e9825113071034f8d917a1b18c2905dc68ad093188af3da4814f18998a751b0e291b38d4cb702cf751b15ce7de09d29aa612a48788b7ce576ba513a50c666404131d2988f57180000012b00000001
```


## 参考

PowStampとPowStampMessageの生成例です。

k値がランダムの為、署名の値は生成毎に異なります。

```
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

```

