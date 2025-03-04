# JSONPOST

JSONPOSTは、Jsonファイルの蓄積システムです。
パブリックドメインに配置してもそこそこ安全にJsonを収集出来る事を目指したシステムです。

Ecdsaによる認証、PoWによるアクセスコントロール、簡易な参照機能を備え、APIによりファイルをアップロードすることができます。


特徴として以下のものがあります。

- サーバーの管理するユーザーの登録情報は公開鍵だけです。
- ユーザーを識別してPoWハッシングによるアクセス制御を行います。
- ECDSA署名によるユーザー識別、詐称防止機能があります。
- PHPとsqlite3で実装されています。安価なVPSで利用できます。


**注意**

このシステムに実装されているPoWは、悪質な攻撃者からの防衛手段としてクライアントに要求される純粋な演算負荷です。
仮想通貨マイニングとは異なり、何の付加価値も生み出しません。

PoWの演算は最大10秒で停止するように実装されていますが、不具合で計算機に高い負荷がかかり続ける可能性は０ではありません。
無人運転は避け、有識者の監督の下に使用するようにお願いします。



# 仕組み
サーバー固有の情報とクライアントの情報を結合した情報にECDSA署名を実施し、ハッシングによる成型後にHttpヘッダと共にファイルをアップロードします。
サーバーはECDSA署名によりクライアントを識別し、さらに操作に応じたハッシング難易度を設定し、その達成条件として、クライアントにPow求め、クライアントからのリクエストを選別します。

ハッシングは必要なリクエスト一度毎に要求されるため、クライアントは情報を乱造することができなくなります。


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









