# Godアカウント専用無制限JSONストレージ

## はじめに

このドキュメントは、**Godアカウントのみがアップロード可能な無制限JSONストレージサーバ**を構築・運用する手順を示します。JCSやスキーマ制限なし、JSONサイズ無制限、アクセス期間制限なしの設定により、公衆ネットワーク上のJSONデータ公開基盤として利用可能です。

---

## 前提条件

- 公開用サーバー or ローカル実行環境でJsonPostが実行されていること
- Python版 jsonpostcl クライアントが利用可能であること

## 手順概要

1. アカウントの生成
2. サーバー初期化（heavendoor 初期化）
3. Godアカウント確認
4. パラメータ設定変更（必要に応じて制限解除）
5. JSONアップロード手順
6. 公開参照方法

---

## 1. アカウントの生成

最初にアカウントを生成します。

```bash
$ python3 jsonclient.py init
```

## 2. サーバー初期化（初回のみ）

```bash
$ python3 jsonpostcl konnichiwa http://127.0.0.1:8000/api \
  --welcome false \
  --json-jcs no \
  --json-no-schema \
  --pow-algorithm "[\"tlsln\",[0.01,1000,100.0]]"
```

#### パラメータ説明（マニュアル準拠）:

- `--welcome <true|false>` : 新規アカウント受付可否を設定。
- `--json-jcs <yes|no>` : JSONデータをJCSフォーマットに正規化して送信するかどうか。
- `--json-no-schema` : JSONスキーマ検証を無効化します。
- `--pow-algorithm <json>` : 使用するPoWアルゴリズムおよびパラメータ設定。この設定はアップロード間隔とファイルサイズに基づき難易度を決定する仕組みで、例えば非常に低負荷で運用したい場合に0.01秒間隔、1000KB中心、標準偏差100などが指定されます。設定内容はJsonPostに搭載されているシミュレータで確認できます。



## 3. Godアカウント確認

```bash
$ python3 jsonpostcl status http://127.0.0.1:8000/api
```

補足: curl でも確認可能です。

```bash
$ curl http://127.0.0.1:8000/api/status.php | jq
```

## 4. パラメータ設定変更（制限解除確認）

必要であれば `heavendoor.php?setparams` を使用して変更可能です。

## 5. JSONアップロード手順

### 例1: コマンドラインでJSONを直接送信

```bash
$ python3 jsonpostcl upload http://127.0.0.1:8000/api \
  --timeout <seconds> \
  --normalize json \
  -J '{"n":1,"a":2}'
```

- `--timeout <seconds>` : タイムアウト時間設定。
- `--normalize json` : JSON文字列を正規化して送信。
- `-J <json>` : コマンドラインから直接JSONを送信。

### 例2: JSONファイルを送信

```bash
$ python3 jsonpostcl upload http://127.0.0.1:8000/api --file example.json
```

- `--file <file>` : JSONファイルを指定してアップロード。

## 6. 公開参照方法

curlで参照する場合は以下のようにします。

```bash
$ curl http://127.0.0.1:8000/api/json.php?uuid=<json_uuid> | jq
```

---

## 注意事項

- Godアカウントの秘密鍵は厳重に保管してください。
- 一度初期化すると、別のGodアカウントへ変更はできません。
- 制限なし設定の場合は高負荷アクセスに備え監視・対策を推奨します。



