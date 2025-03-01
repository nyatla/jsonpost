この仕様は主にsqliteでデータベースファイル全体に関する情報を格納するための定義です。


# 値

- UUID-TEXT UUIDを示す文字列です。
- DATETIME-TEXT 日付と時刻を示す文字列です。最大解像度は秒です。


# テーブルの構成

### properties（データベースプロパティ）

データベース全体に関係するパラメータを格納する名前付き値テーブルです。

```
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,  -- ユニークな識別子
    name TEXT NOT NULL,                  -- 設定項目の名称
    value TEXT NOT NULL                  -- 設定項目の値
);
```


### dbspec（ユーザーテーブルのスペック）

データベースに格納されるユーザーテーブルの名前と、テーブルの規格情報、永続パラメータを格納します。


```
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,  -- ユニークな識別子
    version TEXT NOT NULL,                  -- バージョン情報
    tablename TEXT NOT NULL,                  -- テーブル名
    params TEXT NOT NULL                  -- パラメータ
);
```



<!-- **初期値**

|id|version|tablename|params|
|--|--|--|--|
||jp.nyatla:upload_table|upload_table|| -->