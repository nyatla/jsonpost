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
        "chain": {
            "domain": "main",
            "latest_hash": "bf97792bd9f416e1de6e58c6a46873c043d8bb3485eb0cd83c0372b596d5fd0d",
            "nonce": 0
        },
        "account": null
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
  - **chain**  
    未登録アカウントがアクセス可能なチェーンハッシュの情報を格納します。
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
            "pow_algorithm":[
                "tlsln",[5,0.1,3.8]
            ],
            "welcome":false,
            "json":{
                "jcs":true,
                "schema":{"type":"object","properties":{"name":{"type":"string"},"level":{"type":"number"}}}
            }
        },
        "chain": {
            "domain": "blanch",
            "latest_hash": "bf97792bd9f416e1de6e58c6a46873c043d8bb3485eb0cd83c0372b596d5fd0d",
            "nonce": 0
        },
        "account": {
            "uuid": "01959990-cc38-7217-af39-a1c7bee2ac31"
        }
    }
}
```

### フィールド説明

- **success**  
    処理結果。`true`の場合は成功、`false`の場合は失敗です。
  - **result**  
    Powなしと同一です。
  - **chain**  
    Powなしと同一です。
  - **account**  
    PowStampで識別されたアカウントの情報を格納します。
    - **uuid**  
        アカウントのUUIDです。


---

### 失敗時

エラーが発生した場合は、[`エラーコード`](./errorcodes.md) を参照してください。

## 値の評価

そのアカウントのnonceが知りたい場合はPoWStampを使用して問い合わせを行います。
問い合わせ結果がエラーの場合、0と仮定できます。