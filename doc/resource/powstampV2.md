# PowStampV2仕様

この仕様は[PowStamp](./powstamp.md)の利用実績を踏まえた改良版です。
データ形式のうち、PowStampとPowStampMessageの両方を変更しています。

PowStampV2では、V1のPowNonceとNonceを統合します。

以下の項目を変更しました。

- PowStampMessageのPowNonceをNonceに変更し、バイト長を8に拡大
- PowStampから.PowNonceを削除し、Nonceのバイト長を8に拡大
- PowStampのスコア値算定方式を変更。
- これに伴いハッシング対象をPowStampからPowStampMessageに変更
- ServerNameをServerChainHashに変更


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
| ServerChainHash | 32         | サーバーが提示した現在のSHA256ハッシュ               |
| PayloadHash      | 32         | 送信ペイロードのSHA256ハッシュ            |
| **total**        | **103**    | 合計バイト数                            |

- EcdsaPublicKey  
    クライアントが所有するECDSA秘密鍵から生成します。
- ServerChainHash  
    サーバーから提供されるSHA256ハッシュです。このハッシュは、サーバーがその時点で有効な値を提示します。
    この値の決定方法はサーバの実装に委ねます。
- PayloadHash  
    クライアントが準備する情報で、PowStampと共に送信されるペイロードのSHA256ハッシュ値です。
    ペイロードを持たない場合は `sha256(0[32])` を指定します。
- Nonce  
    Nonceはサーバにより受け入れ下限が提示され、クライアントによりその範囲から選択される値です。
    初期値は0であり、クライアントは常にサーバの提示する値よりも大きい値を選択しなければなりません。  
    この値はPowStampを生成したクライアントの寿命としての側面があります。最大値0x0000ffffffffffff以上の値は存在しないため、サーバの提示値がこの値に到達した時点で、そのPowStampを構成するEcdsaPublicKeyは終端になります。  


## PowStampのスコア

`sha256d(PowStampMessage)` の結果の先頭48ビットを `big-endian UINT` として解釈した値がスコア値です。
スコア値は、値が低いほど出現率が低くなる性質があります。

### 閾値

閾値は、サーバが提示する値で、[0,0x0000ffffffffffff]の範囲にある値です。
サーバーはこの値を閾値と比較し、閾値未満であれば有効なPowStampとして受理します。


### スコアのビジュアライズ

スコアと閾値は値空間に対して指数関数的な分布の確率値です。そのため、ビジュアライズは対数表記で行うべきです。
例えばスコアを[0,1]の範囲で表示する場合は次の計算式が有効です。
```
rate=log2(score)/32
```





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











## PowStamp2を認証機能として使用する

PowStamp2を認証キーとする使用例です。

このモードは、クライアントにアクセス条件を付ける必要のない参照系リクエスト、管理権限リクエストで使用します。

サーバはECDSA署名部分だけを使用してクライアントを識別し、認証します。
認証されたクライアントはサーバに対して無制限に要求が可能です。

PowStampMessageのNonce,ServerChainHash,PayloadHashは0フィルします。


## PowStamp2をアクセス条件として使用する

PowStamp2を認証キーとして、ServerChainHashで制限するリクエストです。

このモードは、nonceを消費しないアクセス条件を付ける場合に使います。

サーバはECDSA署名部分のほかに、ServerChainHashを条件としてクライアントを認証します。
サーバがServerChainHashを提示することで、クライアントがその時点のリクエストを生成する事を条件付けることができます。
Nonceは0フィルします。

リプレイプロテクションが必要なリクエストにも利用できます。



## PowStamp2をペナルティ付アクセス条件として使用する

PowStamp2の本来の使途です。

難易度に応じたペナルティをクライアントに与え、演算量と残存寿命からアクセス制御を試みるモードです。



有効なPowStamp2をサーバに送信するには、以下の条件でPowStamp2のNonceを設定しなければなりません。

- UINT32(sha256d(PowStampMessage(Nonce,ServerChainHash,...))[0:6])<サーバのスコア閾値
- Nonce>サーバの提示したNonce値
- ServerChainHash==サーバの提示したHash値

この条件を満たすために、クライアントはNonceを変化させながらsha256dの結果を評価するプロセスを繰り返し、ハッシングを行います。



