# /heavendoor API仕様

`/heavendoor` は、サーバーの初期化と管理者公開鍵の登録を行うAPIです。

このAPIを利用するには、`PowStamp` ヘッダーが必須です。

## /heavendoor?konnichiwa

未初期化状態のサーバーを初期化して、データベースを構築します。
このAPIは **1度のみ実行可能** で、成功後は二度と使用できません。


### 必要なPowStamp
このAPIは、[`PowStamp`](../../powstamp.md) の付与が必須です。

- **Nonce**: `0` を指定してください。
- **ServerChainHash**: seed_hashを指定します。
- **PayloadHash**: リクエストボディのハッシュです。
- **pow**: ハッシングは不要です。

### リクエストフォーマット

```http
POST /heavendoor.php?konnichiwa HTTP/1.1
PowStamp-1: <hex値>
Content-Type: application/json

{
    "version": "urn::nyatla.jp:json-request::jsonpost-konnichiwa:1",
    "params":{
        "seed_hash":"0000000000000000000000000000000000000000000000000000000000000000",
        "pow_algorithm":["tlsln",[10,16,0.8]],
        "welcome":true,
        "json_schema":null,
        "json_jcs":false        
    }
}
```

### パラメータ説明

- **seed_hash**  
    主チェーンを構成するためのハッシュ値です。安全な乱数であることが必要です。PowStampはこのハッシュ値を使って生成してください。
- **pow_algorithm**  
    サーバーが使用するPoW閾値決定アルゴリズムを指定します。`tnsln` のみ指定可能です。  
    - 詳細は、[`閾値計算アルゴリズム`](../../powstamp.md#閾値計算アルゴリズム) を参照してください。  
    - 上記例では、「アップロード間隔5秒」「JSONファイルサイズ16KB」を目標とした設定になっています。
- **welcome**  
    (省略時:false)新規アカウントのアップロードと登録を受け付けるかを指定します。
- **json_schema**  
    (省略時:null)JSON-Schemaでアップロード可能なJSONを制限する場合に指定します。指定可能なスキーマのバージョンはドラフト6、7です。
- **json_jcs**  
    (省略時:false)アップロード可能なJSONをJCS準拠の物だけに制限するかのフラグです。


---

### レスポンス仕様

#### 成功時

```json
{
    "success": true,
    "result": {
        "genesis_hash": "57ca6c4c15bf786e924521f8c73bef41eda03b9c24ab6dbe9bafb7f761849963",
        "welcome": false,
        "god": "0261747ab20b08b19fbfcc53941a7ef312d969b4d2c95c8bedc2be4ea86977d324",
        "pow_algorithm": [
            "tlsln",
            [
                10,
                16,
                0.8
            ]
        ],
        "json_schema": {
            "type": "object",
            "properties": {
                "name": {
                    "type": "string"
                },
                "level": {
                    "type": "number"
                }
            }
        },
        "json_jcs": true
    }
}
```

#### フィールド説明（リスト形式）

- **success**  
    処理結果。`true`の場合は成功、`false`の場合は失敗です。

- **result**  
    結果を格納します。
    - **genesis_hash**
        ハッシュチェーンの開始ハッシュ値です。この値はsha256(PowStamp)と同じです。
    - **god**  
        登録された管理者の公開鍵です。
    - **その他**  
        設定した値です。  
---

#### 失敗時

エラーが発生した場合は、[`エラーコード`](./errorcodes.md) を参照してください。

## /heavendoor?setparams

サーバの初期化後にパラメータを変更することができます。


### 必要なPowStamp
このAPIは、GODアドレスの[`PowStamp`](../../powstamp.md) の付与が必須です。

- **Nonce**: `0` を指定してください。
- **ServerChainHash**: genesis_hashを指定します。
- **PayloadHash**: リクエストボディのハッシュです。
- **pow**: ハッシングは不要です。

### リクエストフォーマット

```http
POST /heavendoor.php?setparams HTTP/1.1
PowStamp-1: <hex値>
Content-Type: application/json

{
    "version": "urn::nyatla.jp:json-request::jsonpost-setparams:1",
    "params":{
        "pow_algorithm":["tlsln",[10,16,0.8]],
        "welcome":false,
        "json_schema":null,
        "json_jcs":false        
    }
}
```

### パラメータ説明

paramsには、変更するパラメータのみ指定してください。
変更しないパラメータは不要です。

詳細は?konnichiwaのリクエストを参照してください。

---

### レスポンス仕様

詳細は?konnichiwaと同一です。

