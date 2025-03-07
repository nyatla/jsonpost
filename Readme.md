# JSONPOST

**JSONPOST**は、JSONファイルを蓄積・管理するシステムです。  
パブリックドメイン上に設置しても、ある程度安全にJSONデータを収集・保管できる仕組みを目指しています。

このシステムは、以下の機能を備えています：
- **ECDSA（楕円曲線デジタル署名）**によるユーザー認証
- **PoW（Proof of Work）**によるアクセス制御
- **JSONPath**によるデータ参照
- **JCS（JSON Canonicalization Scheme）**によるJSON正規化とデータ選別

---

## 特徴

- ユーザー登録情報として**公開鍵（Public Key）**のみを保持
- ユーザーごとに**PoWによるアクセス制御**を実施
- **ECDSA署名**によるユーザー識別とデータ改ざん防止機能
- **PHP＋SQLite3**で構築され、安価なVPS上で運用可能
- **JSONPath**を使った柔軟なデータ検索機能
- **JSON Schema,JCS（JSON Canonicalization Scheme）**によるJSON正規化と同一性チェック

**注意**

このシステムに実装されているPoWは、悪質な攻撃者からの防衛手段としてクライアントに要求される純粋な演算負荷です。
仮想通貨マイニングとは異なり、何の付加価値も生み出しません。

PoWの演算は最大10秒で停止するように実装されていますが、不具合で計算機に高い負荷がかかり続ける可能性は０ではありません。
無人運転は避け、有識者の監督の下に使用するようにお願いします。



# セットアップ

<精査中>
サーバーはPHP8.0で動作します。拡張モジュールとして、sqlite3,jsonpathが必要です。
その他外部ライブラリは以下ようにインストールしてください。


composer require opis/json-schema
</精査中>

# 使い方

JsonPostはREST-APIを提供します。いくつかのAPIは一般的なHTTPクライアントから直接実行できますが、
認証の必要な機能については特別なHTTPヘッダ[PowStamp](./doc/powstamp.md)を生成する必要があります。

- [/heavensdoor](./doc/server/apis/heavensdoor.md) - 初期設定、管理者操作
- [/status](./doc/server/apis/status.md) - 状態取得
- [/list](./doc/server/apis/list.md) - ドキュメントリストの取得、検索
- [/count](./doc/server/apis/count.md) - ドキュメントリストの統計、検索
- [/json](./doc/server/apis/json.md) - ドキュメントの取得、抽出









