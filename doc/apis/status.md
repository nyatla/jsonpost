# /status API仕様

`/status` は、サーバーの現在の状態を取得するAPIです。

取得できる情報には、サーバーの現在の設定、共通変数、およびアカウント変数が含まれます。
このAPIは、[`PowStamp2`](../../powstamp2.md) の有無によって取得できる情報が変化します。

## PowStamp無しリクエスト

PowStampが付与されていないリクエストの場合、サーバーの共通情報のみ取得できます。
この情報は、サーバが未知の新規アカウントがアクセスするときに使用します。

### リクエスト形式

```http
GET /status.php
```

### レスポンス形式

```json
{
    "success": true,
    "result": {
        "settings": {
            "version": "nyatla.jp:jsonpost:1",
            "pow_algorithm": [
                "tlsln",
                [
                    1,
                    0.01,
                    3
                ]
            ],
            "welcome": false,
            "json": {
                "jcs": false,
                "schema": null
            }
        },
        "chain": {
            "domain": "main",
            "latest_hash": "6dd0fdc192dc97e9929eb2503832cc78c34976f9138e26728ea10042dae98706",
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
    未登録アカウントがアクセス可能なハッシュチェーンの情報を格納します。
    - **domain**
        チェーンのドメインです。mainで固定されます。
    - **latest_hash**
        PowStampの生成に使うパラメータです。
    - **nonce**
        PowStampの生成に使うパラメータです。
  - **account**  
  null（PowStampなしの場合はアカウント情報は含まれません）。

mainドメインのlatest_hashとnonceは、複数のアカウントが共有するチェーンです。このドメインでは、他のアカウントにより値が非同期に更新される可能性があります。  

初回のアクセス操作ではmainドメインのパラメータを使用するため、正しいパラメータで計算したPowStampであっても、外的な要因により無効化される可能性を考慮してください。

---

### 失敗時

エラーが発生した場合は、[`エラーコード`](./errorcodes.md) を参照してください。

---

## PowStamp有りリクエスト

### リクエスト形式

```http
GET /status.php
PowStamp-2: <hex値>
```

[`PowStamp2`](../../powstamp.md) が付与されたリクエストの場合、`account` フィールドにアカウント情報が追加されます。

statusのPowStampは署名機能のみを利用し、以下のパラメータで生成します。

- **Nonce**: `0` を指定してください。
- **ServerChainHash**: `0`フィルを指定してください。
- **PayloadHash**: `0`フィルを指定してください。
- **pow**: ハッシングは不要です。


### レスポンス形式

```json
{
    "success": true,
    "result": {
        "settings": {
            "version": "nyatla.jp:jsonpost:1",
            "pow_algorithm": [
                "tlsln",
                [
                    1,
                    0.01,
                    3
                ]
            ],
            "welcome": false,
            "json": {
                "jcs": false,
                "schema": null
            }
        },
        "chain": {
            "domain": "branch",
            "latest_hash": "492424dfc7b5f4ce70188afa231cb94c4f13a16dd7cdc4715068d330a4806c4e",
            "nonce": 0
        },
        "account": {
            "uuid": "0195a39c-7dbc-70bc-b091-f265bbc4d949"
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
    branch又はmainのハッシュチェーンの情報です。
  - **account**  
    PowStampで識別されたアカウントの情報を格納します。
    - **uuid**  
        アカウントのUUIDです。

PowStampが登録済アカウントにより作成されていれば、domainがbranchのchainを返し、accountに固有情報を返します。
domainがbranchの場合はそのハッシュチェーンはアカウントが占有しているため、外的要因によって値が変化することはありません。

登録されていない場合は、domainがmainのチェーンを返します。この場合は、PowStamp無しと同等の情報です。

---

### 失敗時

エラーが発生した場合は、[`エラーコード`](./errorcodes.md) を参照してください。


