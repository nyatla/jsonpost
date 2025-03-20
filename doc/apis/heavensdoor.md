# /heavendoor API仕様

`/heavendoor` は、サーバーの初期化とGod権限の操作APIです。
このAPIを利用するには、`PowStamp2` ヘッダーが必須です。

## /heavendoor?konnichiwa

未初期化状態のサーバーを初期化して、データベースを構築します。
実行したアカウントをGodアカウントとして登録し、他に定義されているコマンドを独占して利用できるようにします。
このAPIは **1度のみ実行可能** で、成功後は二度と使用できません。


### 必要なPowStamp
このAPIは、[`PowStamp2`](../resource/powstamp2.md) の付与が必須です。


- **Nonce**: `0` を指定してください。
- **ServerChainHash**: seed_hashを指定します。
- **PayloadHash**: リクエストボディのハッシュです。
- **pow**: ハッシングは不要です。

### リクエストフォーマット

```http
POST /heavendoor.php?konnichiwa HTTP/1.1
PowStamp-2: <hex値>
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
    - 詳細は、[`JsonPostのアクセス制御`](../../JsonPostのアクセス制御.md#TimeLogiticsSizeLogNormal方式) を参照してください。  
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
        "properties": {
            "welcome": false,
            "god": "028cb4c857959aa7e319f849cd6dd2a59f264eefa066579dcaa3128d93bda0f4bb",
            "pow_algorithm": [
                "tlsln",
                [
                    1,
                    0.01,
                    3
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
        },
        "chain": {
            "domain": "branch",
            "latest_hash": "0a6981ac171836538199f7830d9e2c2ae5655661798b40d0551ea46418de3048",
            "nonce": 0
        }
    }
}
```

#### フィールド説明

- **success**  
    処理結果。`true`の場合は成功、`false`の場合は失敗です。

- **result**  
    結果を格納します。
    - **god**  
        登録された管理者の公開鍵です。
    - **chain**
      - **latest_hash**
          アカウントの現在のハッシュチェーンの値です。この値はsha256(PowStamp)と同じです。
      - **nonce**
          アカウントの現在のNonce値です。
    - **その他**  
        設定した値です。  




---



#### 失敗時

エラーが発生した場合は、[`エラーコード`](./errorcodes.md) を参照してください。


---

## /heavendoor?setparams

サーバの初期化後にパラメータを変更することができます。


### 必要なPowStamp
このAPIは、GODアドレスの[`PowStamp2`](../../powstamp2.md) の付与が必須です。

- **Nonce**: `0` を指定してください。
- **ServerChainHash**: Godアカウントのハッシュチェーンを指定します。
- **PayloadHash**: リクエストボディのハッシュです。
- **pow**: ハッシングは不要です。

### リクエストフォーマット

```http
POST /heavendoor.php?setparams HTTP/1.1
PowStamp-2: <hex値>
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

konnichiwaと同一です。

```json
{
    "success": true,
    "result": {
        "properties": {
            "welcome": false,
            "god": "028cb4c857959aa7e319f849cd6dd2a59f264eefa066579dcaa3128d93bda0f4bb",
            "pow_algorithm": [
                "tlsln",
                [
                    1,
                    0.01,
                    3
                ]
            ],
            "json_schema": null,
            "json_jcs": false
        },
        "chain": {
            "domain": "branch",
            "latest_hash": "492424dfc7b5f4ce70188afa231cb94c4f13a16dd7cdc4715068d330a4806c4e",
            "nonce": 0
        }
    }
}
```
