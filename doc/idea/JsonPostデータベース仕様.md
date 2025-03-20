

# データベース


Sqlite3の場合、JSONはString,UUID、HASH、PUBKEYはBLOB

## システムテーブル
システムテーブルとして、Nyatla.jpDB管理テーブル標準規格2024のテーブルがあります。

### properties

以下の値を格納します。
- version
- welcome
- root.pow_accept_time

キャッシュ値として以下の値を格納します。

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


### history
Powの必要な操作を記録するテーブルです。
```
CREATE TABLE history (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    timestamp INTEGER NOT NULL,   -- [RO]実行日時（UNIXタイムスタンプを想定）
    id_account INTEGER NOT NULL,  -- [RO]操作を行ったアカウント
    pow_required INTEGER NOT NULL,-- [RO]登録時に要求されていたPowScoreの値
    powstamp BLOB NOT NULL   -- [RO]登録時に使用したPowStamp
);
```
検討事項
pow_paramsについては付帯情報としてpow_params_historyに分離する。
    pow_params JSON NOT NULL      -- [RO]Powの計算に用いられたパラメータ


### json_storage_history
Powテーブルのうち、json_storageに関する付帯情報を記録します。このテーブルの情報はid_historyに結び付けられます。
```
CREATE TABLE json_storage_history (
    id_history INTEGER PRIMARY KEY, -- [OID]
    uuid BLOB NOT NULL,    -- [RO]システム内の文章識別ID
    id_json_storage INTEGER NOT NULL --[RO] 格納されている文章          
);
```

### operation_history
Powテーブルのうち、操作に関する付帯情報を記録します。このテーブルの情報はid_historyに結び付けられます。
一つのid_historyで複数の操作が行われた場合、複数のidに操作を行った順番で登録をします。

```
CREATE TABLE operation_history (
    id INTEGER PRIMARY KEY
    id_history,
    method TEXT NOT NULL, -- [RO]操作の内容
    params JSON  -- [RO]操作パラメータ
);
```

**method**  
- set.god
- set.pow_algolism
- set.json_jcs
- set.json_schema



**opjson**  
このフィールドにはシステムの操作内容をJSON形式で格納します。
格納可能なJSON形式は以下の通りです。


- powアルゴリズム/パラメータを変更します。
  ```[setpow,["algolithm",[p1,p2,p3]]]```



### json_storage
JSONを格納するテーブルです。
```
CREATE TABLE json_storage (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    hash BLOB NOT NULL UNIQUE,         -- [RO]識別子/文章ハッシュ jsonの内容から作成したsha256
    json JSON NOT NULL                 -- [RO]実際のJSONデータ(そのまま保存)
);
```
