# JSONPOST

**JSONPOST** は、JSON ファイルを蓄積・管理する Web API サーバです。簡単な設定でパブリックドメイン上に公開可能な JSON 蓄積・参照サービスを提供します。

このシステムは ECDSA 署名認証と PoW（Proof of Work）を応用したペナルティ要求により、ファイルアップロードを行うクライアントの識別と利用制限を自動化します。この機能は実験的なものです。PoW を使ったアクセス制御の実装自体が目的であり、他のソリューションに対する優位性は特にありません。

## 主な機能

- **ECDSA（楕円曲線デジタル署名）** によるユーザー識別機能
- **PoW（Proof of Work）** を利用したアクセス制限機能
- **JSON Schema、JCS（JSON Canonicalization Scheme）** によるアップロードファイル検証
- **JSONPath** を用いたドキュメント検索機能

---

## Getting Started

### PHP サーバのセットアップ

サーバは PHP 8.3 で動作します。PHP 環境をあらかじめご用意ください。

必要な拡張モジュール：

- sqlite3
- jsonpath （https://pecl.php.net/package/jsonpath）

インストール済みかどうかは `php_info()` で確認できます。

### 外部ライブラリ

以下の外部ライブラリを使用します。

- ramsey/uuid: "^4.7"
- simplito/elliptic-php: "^1.0"
- opis/json-schema: "^2.4"

`server` ディレクトリ内に `composer.json` があるので、以下のコマンドでインストールしてください。

```
composer install
```

### ファイルの配置

ローカルで実行する場合：

```
$ cd jsonpost/server/public
$ php -S 127.0.0.1:8000
```

サーバにデプロイする場合は `server` ディレクトリを設置してください。`client`、`doc`、`webapp` は不要です。

`public` ディレクトリがエントリーポイントです。他のディレクトリは外部公開されないよう設定してください。

### 初期化手順

ローカルの場合：

1. ブラウザで `http://127.0.0.1:8000/` を開き、クライアントダウンロードリンクから ZIP をダウンロードします。
2. ZIP を展開し、以下のコマンドで Python ライブラリをインストールします。

```
pip install ecdsa
```

3. クライアントスクリプトで初期化します。

```
$ python3 jsonpostcl konnichiwa http://127.0.0.1:8000/api --welcome false --json-jcs no
```

初期化オプションは変更可能です。詳細は `--help` を参照してください。

再度 `http://127.0.0.1:8000/` を開き、ステータス画面が表示されれば完了です。

---

## Web UI

`http://127.0.0.1:8000/` から以下の UI にアクセスできます。

- **ステータス** ：サーバ状態を確認
- **統計情報** ：データ件数の取得
- **データ検索** ：格納済みデータの検索
- **クライアントの入手** ：ツールダウンロードリンク

デプロイ方法については [Readme](./webapp/jsonpost-dashboard/README.md) を参照してください。

---

## APIs

JsonPost は REST-API を提供しています。特定の API 呼び出しには PowStamp2 ヘッダが必要です。

- [/heavensdoor](./doc/apis/heavensdoor.md) — 初期設定および管理
- [/status](./doc/apis/status.md) — サーバ状態取得
- [/list](./doc/apis/list.md) — ドキュメントリスト取得
- [/count](./doc/apis/count.md) — データ件数統計
- [/json](./doc/apis/json.md) — ドキュメント取得

詳細な仕様は `doc` フォルダ内を参照してください。

---

## クライアント

Python 実装のクライアントツールを提供しています。WebUI からダウンロード可能です。

詳細は[./client/README.md](./client/README.md) を参照してください。


> ⚠ **注意** ：このクライアントにおける PoW 演算は単なる計算負荷です。無価値な計算を行いますので、過剰な負荷やリスクに注意し、監督下での運用を行ってください。

ソースコードは `client` ディレクトリにあります。

---

## その他ドキュメント

アイデアノートや仕様書は `doc` ディレクトリ内に格納しています。

- [idea](./doc/idea/)
- [resource](./doc/resource/)

