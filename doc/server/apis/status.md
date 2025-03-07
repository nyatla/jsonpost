# /status API仕様

`/status` は、サーバーの現在の状態を取得するAPIです。

取得できる情報には、サーバーの現在の設定、共通変数、およびアカウント変数が含まれます。
このAPIは、[`PowStamp`](../../powstamp.md) の有無によって取得できる情報が変化します。

## PowStamp無しリクエスト

PowStampが付与されていないリクエストの場合、サーバーの共通情報のみ取得できます。

### リクエスト形式

```http
GET /status.php
```

### レスポンス形式

```json
{
    "success":true,
    "result":{
        "settings":{
            "version":"nyatla.jp:jsonpost:1",
            "server_name":null,
            "pow_algorithm":[
                "tlsln",[5,0.1,3.8]
            ],
            "welcome":false,
            "json":{
                "jcs":true,
                "schema":{"type":"object","properties":{"name":{"type":"string"},"level":{"type":"number"}}}
            }
        },
        "root":{
            "latest_pow_time":0
        },
        "account":null
    }
}
```

### フィールド説明

- **success**  
    処理結果。`true`の場合は成功、`false`の場合は失敗です。

- **result**  
    結果を格納します。
  - **setting**  
    現在の設定値を格納します。
    - **version**  
    サーバーのバージョンです。
    - **その他**
    現在の設定値です。
  - **root**  
    アカウント共通の情報を格納します。
    - **latest_pow_time**  
    pow計算に使用される起点時刻[ms-unix-time]です。
  - **account**  
  null（PowStampなしの場合はアカウント情報は含まれません）。

---

### 失敗時

エラーが発生した場合は、[`エラーコード`](./errorcodes.md) を参照してください。

---

## PowStamp有りリクエスト

### リクエスト形式

```http
GET /status.php
PowStamp-1: <hex値>
```

[`PowStamp`](../../powstamp.md) が付与されたリクエストの場合、`account` フィールドにアカウント情報が追加されます。

statusのPowStampは署名機能のみを利用し、以下のパラメータで生成します。

- **nonce**: 0
- **PowStamp閾値**: `0xffffffff`（全ての値を許容）

### レスポンス形式

```json
{
    "success":true,
    "result":{
        "settings":{
            "version":"nyatla.jp:jsonpost:1",
            "server_name":null,
            "pow_algorithm":[
                "tlsln",[5,0.1,3.8]
            ],
            "welcome":false,
            "json":{
                "jcs":true,
                "schema":{"type":"object","properties":{"name":{"type":"string"},"level":{"type":"number"}}}
            }
        },
        "root":{
            "latest_pow_time":0
        },
        "account":{
            "uuid":"01956fd7-d887-7183-ae85-ed42af19b9ec",
            "latest_pow_time":1741348218457
        }
    }
}
```

### フィールド説明

- **success**  
    処理結果。`true`の場合は成功、`false`の場合は失敗です。
  - **result**  
    Powなしと同一です。
  - **root**  
    Powなしと同一です。
  - **account**  
    PowStampで識別されたアカウントの情報を格納します。
    - **uuid**  
        アカウントのUUIDです。
    - **latest_pow_time**  
        アカウントごとのpow計算に使用される起点時刻[ms-unix-time]です。

---

### 失敗時

エラーが発生した場合は、[`エラーコード`](./errorcodes.md) を参照してください。

