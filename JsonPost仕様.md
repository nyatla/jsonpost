# JsonPost仕様

JSONPOSTは、ユーザーがアップロードしたJSONデータを蓄積・管理するシステムです。
JSONPOSTの公開するWebAPIを通じて、ユーザーから送付されるJSONをサーバーに蓄積することができます。

ユーザーの識別は行いますが管理は省略されているため、ユーザーの登録などの面倒な作業が不要です。

サーバはPHPとsqlite3です。クライアントはpython3のサンプルがあります。


## 機能

以下の機能を搭載しています。

- データの蓄積・参照機能
- アップロードユーザーの識別機能
- ECDSA署名によるユーザー管理、詐称防止機能
- ハッシングによる負荷対策機能。


# 動作概要

本システムは、セッションを持たない単発認証通信によって動作し、クライアントがサーバーを操作する仕組みです。各クライアントは独自のECDSA秘密鍵を保持し、サーバーはその対応するECDSA公開鍵でクライアントを識別します。クライアントは所定の方式に従ってリクエストを作成し、リクエストに署名を付加して送信します。サーバーは受信したリクエストが正式なものであるかを検証し、正当な場合にのみ、機能が実行されます。


機能には、サーバーの状態を変更する操作リクエストと、状態を変更を伴わない参照リクエストがあります。現状では操作リクエストのみが署名を要求し、参照リクエストは一般的なHTTP参照シーケンスと同様な動作をします。



## 操作リクエスト

操作リクエストの場合、クライアントはECDSA署名によりリクエストを証明し、また、演算コストを支払い、その署名を所定の書式PoWStampに整え、サーバに送信します。この情報は、手紙に張り付ける切手のようなイメージで捉えてください。


PowStampには、以下の情報が含まれ、それぞれ目的があります。

**署名**

ECDSAで作成した署名値(256bit)です。情報全体が公開鍵の所有者によって作成されたことを証明します。

**Publicキー**

署名検証のために必要です。

**nonce値**

リクエストの通し番号です。この値はサーバーにも保存されていて、リクエストが成功する度に更新されます。新しい値は常に古い値より大きくなければならず、同一な値を持つリクエストは無効になり、同一ドメイン内でのリプレイが不可能になります。


**サーバードメイン値**

署名する文章には、署名の送信先のサーバーを識別するドメイン名のハッシュ値を指定します。この値はサーバにも保存されていて、一致しない限りリクエストは成功しません。別ドメインでのリプレイが不可能になります。

**PoW-Nonce値**

PowStampのハッシュ値の先頭からNbitを0に揃えるために演算力を要求し、署名の精製コストを指数的に増加させます。署名の乱造が不可能になります。

**ドキュメントハッシュ値**

送信するペイロードの詐称を防止します。


PowStampは操作リクエストヘッダにhex文字列で書き込みます。ハイフンの後ろはバージョン番号です。異なるバージョンが併記された場合はサーバーが理解できる最大のバージョンを有効値とします。同一のバージョンが複数併記された場合は最も後に宣言されたものを有効とみなします。
```
PowStamp-1: 0102.....
```

## シーケンス

初期化

アップロード

参照







# 署名形式

### POW_STAMP
POW_STAMPは、クライアントがサーバーの操作系APIを呼び出すときにHttpヘッダに付加するバイトデータです。
1回限りの使い捨ての情報です。 APIが受理されるたび作り直す必要があります。

POW_STAMPは、ECDSA署名(EcdsaSignature)、ECDSA署名を検証するためのメッセージ(Message)、PoWハッシングで得るPowNonce値(PowNonce)で構成します。

**POW_STAMP**
|フィールド名|サイズ(byte)||
|--|--|--|
|PowStampSignature|64|ECDSA.sign(PowStampMessage)|
|EcdsaPublicKey|33|プレフィクス付キー|
|Nonce|4|メッセージNonce|
|PowNonce|4|ハッシングNonce|
||||
|total|105||

結合した値を205文字のhex値として送信します。
PowStampSignatureは以下のバイト列のSHA256ハッシュです。

**PowStampMessage**
|フィールド名|サイズ(byte)||
|--|--|--|
|EcdsaPublicKey|33|プレフィクス付キー|
|Nonce|4|メッセージNonce|
|ServerDomainHash(sha256)|32||
|PayloadHash(sha256)|32||
||||
|total|101||


ServerDomainHashは、Publicサーバーの場合は0を指定します。
PayloadHashは、ペイロードを持たない場合は0を指定します。

この２つのパラメータは、サーバー側の固有パラメータをリクエストの一部であるため、POW_STAMPには含まれません

### POW_STAMPのスコア

sha256d(POW_STAMP)で得たハッシュ値の先頭から、連続する0ビットの数がスコアになります。
サーバーはPOW_STAMPのスコアを内部の記録と比較し、閾値を下回ったものについては処理を拒否します。







# データベース


## システムテーブル
システムテーブルとして、Nyatla.jpDB管理テーブル標準規格2024のテーブルがあります。

### properties
以下の値を格納します。
rootkey hexstr 管理者のpublicキー



##　アプリケーションテーブル



ユーザーテーブルとして、以下のテーブルを定義します。

- account_root
  ユーザーの識別情報、状態管理テーブルです。
- json_storage
  JSONデータを格納します。
- json_history




### account_root
```
CREATE TABLE account_root (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    pubkey TEXT NOT NULL, --[RO] ecdasのrecoverkey[0]
    uuid TEXT NOT NULL,   --[RO] ユーザー識別のためのuuid
    nonce INTEGER NOT NULL,   --[RW] 署名データの下位8バイト(nonce)
    status INTEGER DEFAULT 1  --[RW] 状態。1のみ
);
```

このテーブルはecdasパブリックキーとnonce値を書き込みます。nonceは新たなリクエストが成功する度にインクリメントします。
pubkey,uuidは永続データです。

ユーザーの識別にはuuidを使用します。が、そのuuidを識別するためには署名のリカバリキーの候補とpubkeyで識別します。

pubkeyは圧縮したrecoverkey[0]です。


### json_storage

```
CREATE TABLE json_storage (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    uuid BLOB NOT NULL,                -- [RO]システム内の文章識別ID
    hash BLOB NOT NULL,                -- [RO]識別子/文章ハッシュ jsonの内容から作成したsha256
    json JSON NOT NULL                 -- [RO]実際のJSONデータ（そのまま保存）
);
```

このテーブルはアカウントが投入したJSONを蓄積します。同一文章は格納できません。

### json_storage_history
```
CREATE TABLE json_storage_history (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    created_date INTEGER NOT NULL,     -- [RO]データの投入時刻（UNIXタイムスタンプを想定）
    id_account INTEGER NOT NULL,       -- [RO]文章を所有するアカウントID
    id_json_storage INTEGER NOT NULL,   -- [RO]文章のID
    opcode INTEGER NOT NULL,   -- [RO]操作コード(0)
    powbits INTEGER NOT NULL --[RO]登録時のPOWビット数
);
```
このテーブルはアカウントが投入したJSONとアカウントのIDペア、その投入を記録します。
json_storageと組み合わせて、アカウントが投入したJSONと投入日時を識別することができます。





<!-- ## json_summary_table
```
CREATE TABLE json_storage (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    id_json_storage INTEGER NOT NULL,   -- [RO]文章のID
    version TEXT --存在するならjson内のversionの値
    size INTEGER NOT NULL --jsonの格納サイズ
);
```
jsonのサマリーを格納します。サマリはjson_storageのサブセットで、任意のタイミングで再構成できなければなりません。
必要に応じて列を追加してください。 -->

---

# API

## /heavendoor
操作リクエストです。初期状態のデータベースを作成し、公開鍵を管理者として登録します。このAPIは１度しか成功しません。
このAPIの実行にはhttpヘッダにPowStamp-1が必要です。

```
POST　/heavendoor?konnichiwa
{
    "version":"urn::nyatla.jp:json-request::ecdas-signed-konnichiwa:1",
    "params":{
        "pow_bits_write":1,
        "pow_bits_read":1,
        "server_name":null        
    }
}
```
server_nameに指定した名前はサーバーのドメイン名に設定され、以降のPowStamp-1でを生成するときに必要になります。
この値が同一なサーバーでは、他の同一名のサーバーに使用されたPowStamp-1が最大１度だけ使用可能です。




**成功**
```
{
    "success":true,
    "result":{
        "godkey":[登録された公開鍵]
    }
}
```
この公開鍵は管理者機能を使うために必要です。


**失敗**
```
{
    "success":false,
    "message":""
}
```



## /upload

操作リクエストです。サーバーにJsonの登録を行います。
このAPIの実行にはhttpヘッダにPowStamp-1が必要です。


```
POST /upload
#JSON文章
```

**成功**
PowStampから識別したユーザーの情報、登録した文章の情報を返します。

```
{
    "success":true,
    "result":{
        "user_uuid":[操作を行ったユーザーのUUID],
        "json_uuid":[ドキュメントのUUID],
        "score":{
            powbits:4
        }
    }
}
```

**失敗**
```
{
    "success":false,
    "message":""
}
```

- nonceが記録より若い
- 署名検証の失敗
- JSON形式のエラー
- PoWスコアの不足

## 処理手順

登録データの検証は次のように行います。
アカウントとドキュメントのuuidはサーバーが採番します。

account_rootに存在しないアカウントの場合はアカウントを登録します。
json_storageに存在しない文章ならjson_storageにJSONを格納します。
json_holderにアカウントと文章idペアを登録します。

全ての処理が正常に終了した時のみ、nonceがPowStamp-1に指定した値に更新されます。




## /version
システムのバージョンを返します。
```
GET version.php
```

**成功**
```
{
    "success":success,
    "result":{
        "version":"バージョン"
    }
}
```




## /jsonlist

JSONドキュメントの検索APIです。

**パラメータ**
- index|page|uuid
- limit


返されるデータは、uuid,ダイジェストです。
返却数の最大はlimitで指定します。省略した場合はlimit=100です。

指定できるパラメータは３種類あります。

- index 登録順に先頭からの番号を返します。

- page indexの亜種です。limitと組み合わせて、limit*pageをインデクスとして計算します。

- uuid ドキュメントuuidをキーとして検索し、それ以降に登録された項目を返します。

未実装

- account アカウントのuuidまたはパブリックキーで登録されたドキュメントを返します。
- title 先頭からN文字をタイトルとして文字列で追記します。

いずれか一つのパラメータを指定できます。省略された場合はindex=0です。




index,page,uuid_afterはlimitを指定できます。

全て省略時はindex=0,limit=100です。


```
GET /getjsonentries.php
```

**成功**
```
{
    "success":true,
    "result":{
        "items":[
            [created_date_int,uuid,document_hash]
        ],
        "index":[返却値の始点]
        "total":[対象レコードの総数]

    }

}
```

totalとindexは呼出し毎に異なる場合があります。

## /jsondoc

JSONドキュメントの参照APIです

- uuid
- raw 指定するとjson部分だけが返ります。

```
GET /jsondoc.php?uuid=00000000-0000-0000-000000000000
```

**成功**
```
{
    "success":true,
    "result":{
        "uuid":00000000-0000-0000-000000000000,
        "json":[:JSONドキュメント:]
    }
}
```

