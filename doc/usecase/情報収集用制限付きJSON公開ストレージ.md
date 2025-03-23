# 情報アップロード用制限付きJSON公開ストレージ

## はじめに

このドキュメントは、**不特定多数のユーザーから情報をアップロード可能な、制限付きJSON公開ストレージサーバ**を構築する手順を説明します。

途中までは「Godアカウント専用・無制限ストレージ」の構築と同様の手順ですが、初期化後にパラメータを調整し、特定の条件下で広くユーザーに開放できるように設定を行います。

これにより、JCS適用、JSONスキーマ制約、難易度パラメータを設定し、適切な利用頻度と投稿内容が守られた運用が可能になります。

---

## 前提条件

- 公開サーバー または ローカル環境にJsonPostサーバーがセットアップ済み
- Python版 jsonpostcl クライアントが利用可能であること

## 手順概要

1. アカウントの生成
2. サーバー初期化（heavendoor 初期化）
3. Godアカウント確認
4. パラメータ設定変更（公開設定への変更）
5. JSONアップロード手順（一般ユーザー向け）
6. 公開参照方法

---

## 1. アカウントの生成

```bash
$ python3 jsonclient.py init
```

## 2. サーバー初期化（初回のみ）

まずはGodアカウント専用環境として初期化を行います。

```bash
$ python3 jsonpostcl konnichiwa http://127.0.0.1:8000/api \
  --welcome false
```

### 使用パラメータ例:

- `--welcome false` : 初期状態で新規アカウント受付を禁止。

> アルゴリズムなどの詳細設定は後で行うため、初期化時は新規アカウント受付を無効化します。

## 3. Godアカウント確認

Godアカウントが正常に登録されているか確認します。

```bash
$ python3 jsonpostcl status http://127.0.0.1:8000/api
```

> 補足: curl でも確認可能です。

```bash
$ curl http://127.0.0.1:8000/api/status.php | jq
```

## 4. パラメータ設定変更（公開用設定）

Godアカウントで設定変更を行い、不特定多数のユーザーがアップロード可能な状態に切り替えます。

```bash
$ python3 jsonpostcl setparams http://127.0.0.1:8000/api \
  --welcome true \
  --json-jcs yes \
  --json-schema <jsonschema-filename> \
  --pow-algorithm "[\"tlsln\",[30,8,0.5]]"
```

### 使用パラメータ例:

- `--welcome true` : 新規ユーザー登録を許可。
- `--json-jcs yes` : JCS正規化を強制適用。
- `--json-schema` : 許可するJSON構造を定義するスキーマファイル。
- `--pow-algorithm` : 利用頻度やサイズに応じたPoW設定。

> json-jcs と json-schema により受け入れるJSONの型を固定できます。json-jcs を無効化すると不必要な差分が別物として扱われるため非効率です。json-schema は情報収集用途に適した入力フォーマット管理に有効です。

#### pow-algorithm の詳細:

この設定は TimeLogiticsSizeLogNormal (TLSLN) 方式に基づく PoW 難易度設定です。

- 最初の値: 推奨アップロード間隔（秒）
- 2番目の値: 推奨JSONサイズ（KB）
- 3番目の値: 標準偏差（σ）、許容範囲調整

例として `30, 8, 0.5` は「30秒間隔・8KBファイル中心・σ=0.5」の意味で、逸脱するとPoW負荷が上昇します。JsonPostに搭載のシミュレータで確認可能です。

## 5. JSONアップロード手順（一般ユーザー向け）

```bash
$ python3 jsonclient.py init
$ python3 jsonpostcl upload http://127.0.0.1:8000/api --normalize jcs --file user_message.json
```

### 使用パラメータ例:

- `--file <file>` : アップロードするJSONファイルを指定。
- `--normalize jcs` : サーバーでjcsが有効になっている場合、JSONをJCS準拠に成型して送信するオプション。

## 6. 公開参照方法

```bash
$ curl http://127.0.0.1:8000/api/json.php?uuid=<json_uuid> | jq
```

- `<json_uuid>` : アップロード時に発行されたUUIDを指定して参照。

