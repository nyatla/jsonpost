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




# データベース


## システムテーブル
システムテーブルとして、Nyatla.jpDB管理テーブル標準規格2024のテーブルがあります。

### properties

以下の値を格納します。
- god hexxst 管理者publicキー
- powalgorithm powアルゴリズムの種類.指定可能値は後述


#### powalgorithm
指定可能な値は以下の通りです。

**tlsln**
TimeLogiticsSizeLogNormal(et,s,s_sigma)

書式 tlsln(10,16,.8)
- et 目標アップロード間隔(s)
- s 目標ファイルサイズ。(kb)
- s_sigma ファイルサイズの分散σ値

TimeLogiticsSizeNormalsOr(eh,s,s_sigma)

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
    pubkey TEXT NOT NULL, --[RO] publickey
    uuid TEXT NOT NULL,   --[RO] ユーザー識別のためのuuid
    nonce INTEGER NOT NULL,   --[RW] インクリメンタルnonce
);
```

このテーブルはecdasパブリックキーとnonce値を書き込みます。nonceは新たなリクエストが成功する度にインクリメントします。
pubkey,uuidは永続データです。

ユーザーの識別にはuuidを使用します。が、そのuuidを識別するためには署名のリカバリキーの候補とpubkeyで識別します。



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


<!-- ### json_spec
```
CREATE TABLE json_storage (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    uuid BLOB NOT NULL,                -- [RO]システム内の文章識別ID
    hash BLOB NOT NULL,                -- [RO]識別子/文章ハッシュ jsonの内容から作成したsha256
    json JSON NOT NULL                 -- [RO]実際のJSONデータ（そのまま保存）
);
```
このテーブルは補助的なテーブルです。JSONの種別を -->


### json_storage_history
```
CREATE TABLE json_storage_history (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    created_date INTEGER NOT NULL,     -- [RO]データの投入時刻（UNIXタイムスタンプを想定）
    id_account INTEGER NOT NULL,       -- [RO]文章を所有するアカウントID
    id_json_storage INTEGER NOT NULL,   -- [RO]文章のID
    opcode INTEGER NOT NULL,   -- [RO]操作コード(0)
    pownonce INTEGER NOT NULL --[RO]登録時のPOW-NONCE数
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
        "server_name":null,
        "pow_algorithm":"tnsln(5,64000,.8)"
    }
}

```
server_nameに指定した名前はサーバーのドメイン名に設定され、以降のPowStamp-1でを生成するときに必要になります。
この値が同一なサーバーでは、他の同一名のサーバーに使用されたPowStamp-1が最大１度だけ使用可能です。

pow_algorithmは```アルゴリズム名(パラメータ)```で指定します。
現在指定できるのはtnslnのみです。この例では、アップロード間隔のターゲット5秒、JSONファイルサイズのターゲット64kbを中心にしています。


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

- ペイロードがJSON形式を満たさない。
- 署名検証の失敗。
  - サーバー名の不一致
  - nonceが不正。
  - PoWスコアの不足
  - 正しくない署名

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




## /list.php

JSONドキュメントの検索APIです。
ページネーションで指定した範囲にあるドキュメント情報をリストで返します。


**パラメータ**
- **index**|**uuid**
  リストの先頭を識別します。indexは先頭からの番号を返します。uuidはドキュメントを識別し、そのうち最も古い物を先頭にします。
  省略時はindex=0です。
- **page**
  indexの亜種です。limitと組み合わせて、limit*pageをインデクスとして計算します。
  省略時はpage=0です。
- **limit**
  返却する項目の最大数を指定します。
  省略時はlimit=0です。
- **path**
  返却するJSONのうち、pathに指定される要素が存在するものだけを抽出します。
  省略時は無視します。
- **value**
  pathに指定される要素が一致するものだけを抽出します。
  省略時は無視します。


返却数の最大はlimitで指定します。省略した場合はlimit=100です。pathとvalueによる抽出は、inxex,limitで指定した範囲のレコードから再選択します。例えば1000件のレコードに対してindex=100,limit=100を指定した場合、100件目から100レコードが先に選択され、そのなかから再度条件に一致するレコードを選択します。この範囲に条件に一致するレコードがなければ戻り値は0となります。

pathはSqliteのjson-1の関数であるjson_extractの機能を利用しているため、使用できる構文に制限があります。条件式、演算式は使用できません。
https://www.sqlite.org/json1.html#jexb

valueはパスで選択されたノードの文字列表現値と完全に一致する必要があります。


未実装

- title 先頭からN文字をタイトルとして文字列で追記します。


totalとindexは呼出し毎に異なる場合があります。


```
GET /list.php
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

## /count.php

JSONドキュメントの統計APIです。
ページネーションで指定した範囲にあるドキュメントの統計を返します。



**パラメータ**
/list.phpと同一です。

```
GET /count.php
```

**成功**
```
{
  "success": true,
  "result": {
    "matched": 24,
    "total": 24
  }
}
```



## /json.php

JSONドキュメントの参照APIです。

- uuid
- raw 指定するとjson部分だけが返ります。
- path 対象の文章を成型するjsonpathです。

path値はphpのjsonpath拡張モジュールで処理します。これはsqliteのjson-1よりも幅広い式を受け付けます。


```
GET /json.php?uuid=00000000-0000-0000-000000000000
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

# 攻撃手法と防衛策

## 既存ユーザーの高頻度アクセス
- 同一なトランザクションを繰り返し送る
  ✅署名に加算nonceを組み込んでによって識別する。

- 異なるトランザクションを高頻度で送る
  動的難易度変更で時間当たりの難易度を平坦化する。

- 事前計算による一括送信
  動的難易度変更により事前計算を無効にする。
  ハッシュチェーンで署名に予測不能nonceを要求する。



## 未知ユーザーの高頻度アクセス
- 短時間に大量の新規アカウントを作成する
  事後評価により登録テーブルからの抹消。
  単位時間当たりの新規登録を制限する。


## 別サーバー間での問題
- トランザクション混入
  ✅サーバー識別子を署名に組み込む