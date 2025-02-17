# JSONPOST

JSONPOSTは、ユーザーがアップロードしたJSONデータを蓄積・管理するシステムです。
JSONPOSTの公開するWebAPIを通じて、ユーザーから送付されるJSONをサーバーに蓄積することができます。

ユーザーの識別は行いますが、管理は省略されているため、ユーザーの登録などの面倒な作業が不要です。

安いPHPサーバーで動作するように設計されています。



## 機能
- データの蓄積・参照機能
- アップロードユーザーの識別機能
- ECDSA署名によるユーザー管理、詐称防止機能
- ハッシングによるいたずら対策(OPTION)


サーバはPHPとsqlite3です。
クライアントはpython3のサンプルがあります。










## セキュリティ

サーバーに書き込みを行う操作については、ユーザー側で生成したECDSA秘密鍵の署名と公開鍵で検証を行います。
サーバーは公開鍵と内部識別子を結びつけ手管理します。


### リプレイプロテクション

**nonce**を利用したリプレイ攻撃防止機能

- **不正署名**の場合、登録は拒否されます。
- **正式署名＋下位nonce**の登録は署名検証に失敗します。
- 署名検証に成功した場合、文章と登録日時が記録されます。初回はユーザーも同時に登録されます。

**PoW**を利用した連続アクセス防止機能

- 署名にハッシングパラメータを加えることで非対称な計算コストを発生させ、連続アクセスを防止します。
- 規定に達しない署名は検証に成功しても受理されません。
- 共通規定のほかに、アカウントの信頼度に合わせて規定を設定できます。
  
## 署名形式

### ECDSA_SIGN_POW_S64K33N4P4

この署名はアップロード操作に使う署名です。秘密鍵生成者の証明のみを目的としています。
ECDSAの署名、公開鍵に、32bitNONCE、32bitのPOWパラメータを加えたバイトストリームです。ペイロードの詐称対策はできません。


#### 検証文字列の生成
1. メッセージを生成する。
   4バイト32bit(BE)のnonce値(unsigned int)をN4とする。
2. 署名を生成する。
   N4のsha256ハッシュにecdsaで署名し、S64とする。
3. 短縮publicキーを生成
   秘密鍵から33バイトの短縮pubキーK33に生成する。
4. 署名バイト列を生成
   S64、P33,N4を署名バイト列とする。
5. PowのNonceを探索
   S64K33N4に4バイトのPowパラメータP4を連結した値のsha256dを計算する。
   末尾の連続する0ビットの数が規定値を超えるまで、N4を交換してハッシングする。
6. 規定を超えたS64K33NP4を検証文字列とする。


#### Nonceの値

Nonceの初期値は 0 です。採番方式はクライアントに依存します。代表的な方式として、記録なし採番の場合、2000年1月1日からの経過秒数を基に行います。この場合、1分間あたり60トランザクションを処理可能で、2000年1月1日から約 2156年2月7日にかけて使用可能です（この日を過ぎると、非対応となります）。記録ありの場合、最大で 4294967295回 使用可能です。nonceを意図的に最大値にすることで、機能を無効化できます。


**署名方式: ECDAS_SIGN_S64P33X**
この署名はペイロードを送信する署名です。
ECDSAの署名、公開鍵に、Xバイトのバイト列が続くきます。

Xのバイト列をメッセージとして署名します。



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
    id_json_storage INTEGER NOT NULL   -- [RO]文章のID
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
## /heavendoor
初期状態のデータベースを作成し、公開鍵を管理者として登録します。このAPIは１度しか成功しません。

```
POST　/heavendoor?konnichiwa
{
    "version":"urn::nyatla.jp:json-request::ecdas-signed-konnichiwa:1",
    "signature":[:ECDAS_SIGN_S64P33X:],
    "params":{
        "upload_pow_bits":16,
    }
}
```
[:ECDAS_SIGN_S64P33X:]は、メッセージに"konnichiwa"を指定します。

- upload_pow_bits ECDAS-NONCE-SIGN-S64P33PX

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

アップロードAPIは、クライアントから検証文字列とJSONデータを受け取ります。

account_rootに存在しないアカウントの場合はアカウントを登録します。
json_storageに存在しない文章ならjson_storageにJSONを格納します。
json_holderにアカウントと文章idペアを登録します。


```
POST　/upload
{
    "version":"urn::nyatla.jp:json-request::ecdas-signed-upload:1",
    "signature":[:ECDSA_SIGN_POW_S64K33N4P4:],
    "data":[:JSON:]
}
```

**成功**
```
{
    "success":true,
    "result":{
        "user":{
            "uuid":[操作を行ったユーザーのUUID]
        },
        "document":{
            "uuid":[ドキュメントのUUID],
        }
    }
}
```
署名から識別したユーザーの情報、登録した文章の情報を返します。

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
- PoWランクの不足

## 処理手順

登録データの検証は次のように行います。
アカウントとドキュメントのuuidはサーバーが採番します。



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
    "items":[
        [created_date_int,uuid,document_hash]
    ],
    "index":[返却値の始点]
    "total":[対象レコードの総数]

}
```

totalとindexは呼出し毎に異なる場合があります。

## /jsonitem

JSONドキュメントの参照APIです

- uuid
- rawjson 指定するとjson部分だけが返ります。

**成功**
```
{
    "success":true,
    "result":{
        "uuid":[ドキュメントのuuid],
        "json":[JSONドキュメント]
    }
}
```

