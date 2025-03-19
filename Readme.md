# JSONPOST

**JSONPOST**は、JSONファイルを蓄積・管理するWebAPIサーバです。  
ECDSA署名認証とPoWを応用したアクセス制御により、パブリックドメイン上に設置しても
ある程度安全にJSONデータを収集・保管できる仕組みを目指しています。

このシステムは、以下の機能を備えています：

- **ECDSA（楕円曲線デジタル署名）** によるユーザー識別機能
- **PoW（Proof of Work）**　を利用したアクセス制限機能
- **JSON Schema,JCS（JSON Canonicalization Scheme）**  によるアップロードファイルの選別機能
- **JSONPath** による文章検索機能
  




## GettingStarted

### PHPサーバのセットアップ

サーバーはPHP8.3で動作します。PHPをセットアップしておいてください。


拡張モジュールは以下を要求します。

- sqlite3
- jsonpath https://pecl.php.net/package/jsonpath

セットアップされているかphp_info()で確認してください。


外部ライブラリとして以下を要求します。

- ramsey/uuid: "^4.7"
- simplito/elliptic-php: "^1.0"
- opis/json-schema: "^2.4"

serverディレクトリにcomposer.jsonがありますので、``composer install``でインストールしてください。

### ファイルの配置




ローカルで実行する場合は、以下のコマンドラインで起動します。

```
$cd jsonpost/server/public
$php -S 127.0.0.1:8000
```

サーバに設置する場合はserverディレクトリを展開して下さい。
client,doc,webappのディレクトリは不要です。

publicディレクトリがアプリケーションのエントリーポイントです。
それ以外のディレクトリは外部に公開されないように設定してください。



## 初期化






# APIs

JsonPostはREST-APIを提供します。いくつかのAPIは一般的なHTTPクライアントから直接実行できますが、
認証の必要な機能については特別なHTTPヘッダ[PowStamp2](./doc/powstamp2.md)を生成する必要があります。

- [/heavensdoor](./doc/server/apis/heavensdoor.md) - 初期設定、管理者操作
- [/status](./doc/server/apis/status.md) - サーバ状態取得
- [/list](./doc/server/apis/list.md) - ドキュメントリストの取得、検索
- [/count](./doc/server/apis/count.md) - ドキュメントリストの統計、検索
- [/json](./doc/server/apis/json.md) - ドキュメントの取得、抽出



# クライアント

PowStamp2の必要な操作を行うためのクライアントが使用可能です。







**注意**

このシステムに実装されているPoWは、悪質な攻撃者からの防衛手段としてクライアントに要求される純粋な演算負荷です。
仮想通貨マイニングとは異なり、何の付加価値も生み出しません。

PoWの演算は固定時間で停止するように実装されていますが、不具合で計算機に高い負荷がかかり続ける可能性は０ではありません。
無人運転は避け、有識者、または有識者の監督の下に使用するようにお願いします。
