
## JSONPOST クライアント使用方法

### はじめに  
このクライアントは JSONPOST サーバーと通信し、署名付き JSON データをアップロードするほか、サーバー初期化やパラメータ設定を行う CLI ツールです。  
実行には Python 3、および依存モジュール (`requests`, `ecdsa` など) が必要です。  

---

### 1️⃣ 初期設定（設定ファイル作成）
```bash
python3 jsonpostcl init [ファイル名]
```
- 設定ファイル (`jsonpost.cfg.json`) を生成します。  
- `ファイル名` を省略すると `./jsonpost.cfg.json` が作成されます。  

---

### 2️⃣ サーバ初期化 (konnichiwa)
管理者のみ実行可
```bash
python3 jsonpostcl konnichiwa <endpoint>  --pow-algorithm '["tlsln",[10,16,0.8]]'  --welcome true  --json-jcs false  --json-schema <schema.json>
```

| オプション            | 説明 |
|--------------------|------|
| `<endpoint>`      | サーバーのAPIエンドポイント（例: `http://127.0.0.1:8000/api`） |
| `--pow-algorithm` | サーバーが使用するPoW難易度計算のアルゴリズム。デフォルトは `["tlsln",[10,16,0.8]]`<br>（10秒間隔目標、16KBピークサイズ、分布σ=0.8） |
| `--welcome`       | 新規アカウント登録を許可するかどうか。`true` で許可、`false` で拒否 |
| `--json-jcs`      | **JSONアップロード時にJCSフォーマットのみを許可するかどうか**。<br> - `true` を指定すると JCS 形式のみアップロード可能になります。<br> - `false` を指定すると通常のJSON形式も受け入れ可能です。<br>※ JCS (JSON Canonicalization Scheme) は JSON データの標準化手法です。 |
| `--json-schema`   | アップロード時にバリデーションする **JSONスキーマファイル** を指定します。<br> - `schema.json` などで指定します。<br> - `null` もしくは指定しなければスキーマチェックなしとなります。 |

---

### 3️⃣ JSON アップロード
```bash
python3 jsonpostcl upload <endpoint>  -F <filename.json>  -C <configファイル>  --normalize jcs  --timeout 5  --rounds 3
```

| オプション         | 説明 |
|-----------------|------|
| `<endpoint>`    | アップロード先エンドポイント |
| `-F` または `--filename` | アップロードする JSON ファイルパス |
| `-J` または `--json` | JSON 文字列を直接指定する場合 |
| `-C` または `--config` | 設定ファイルパス（デフォルト: `./jsonpost.cfg.json`） |
| `--normalize`   | アップロード前に JSON を `raw`, `json`, `jcs` のいずれかに整形して送信 |
| `--timeout`     | PoW 計算 1 回あたりのタイムアウト (秒) |
| `--rounds`      | PoW 計算ラウンド数 |

---

### 4️⃣ サーバーパラメータ変更 (setparams)
```bash
python3 jsonpostcl setparams <endpoint>  --pow-algorithm '["tlsln",[10,16,0.8]]'  --welcome false  --json-jcs true  --json-schema <schema.json>  --json-no-schema
```
- `--json-no-schema` を指定すると JSON スキーマチェックを無効化します。  
- `--json-schema` を指定すると新しいスキーマに設定します。  

---

### 5️⃣ ステータス取得
```bash
python3 jsonpostcl status <endpoint> -A -U
```
| オプション | 説明 |
|------------|------|
| `-A`       | アカウント情報も取得 |
| `-U`       | サーバーの情報をローカル設定に反映 |

アップロード捜査には、アカウントごとの設定が必要です。-A,-Uオプションをつけて実行してください。

---

### 注意事項
- PoW 計算は高負荷となる場合がありますので、十分注意してご利用ください。
- サーバー側が `205` エラーで返してきた場合は `required_score` に基づき再試行を自動で行います。
- クライアント設定ファイルは秘密鍵を含みますので厳重に管理してください。
