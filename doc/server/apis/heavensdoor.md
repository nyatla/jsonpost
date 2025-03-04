# /heavendoor API仕様

`/heavendoor` は、サーバーの初期化と管理者公開鍵の登録を行うAPIです。
このAPIは **1度のみ実行可能** で、成功後は二度と使用できません。

このAPIを利用するには、`PowStamp` ヘッダーが必須です。

## リクエスト仕様

### 必要なPowStamp
このAPIは、[`PowStamp`](../../powstamp.md) の付与が必須です。

- **nonce**: `0` を指定してください。
- **payload**: リクエストボディの内容です。
- **pow**: ハッシングは不要です。

### リクエストフォーマット

```http
POST /heavendoor.php?konnichiwa HTTP/1.1
PowStamp-1: <hex値>
Content-Type: application/json

{
    "version": "urn::nyatla.jp:json-request::jsonpost-konnichiwa:1",
    "params":{
        "pow_algorithm":["tlsln",[10,16,0.8]],
        "server_name":null
    }
}
```

### パラメータ説明

- **server_name**  
    サーバーのドメイン名として登録します。  
    以降のPowStamp生成時に必要になります。  
    同名サーバー間では認証情報を共有できます。

- **pow_algorithm**  
    サーバーが使用するPoW閾値決定アルゴリズムを指定します。`tnsln` のみ指定可能です。  
    - 詳細は、[`閾値計算アルゴリズム`](../../powstamp.md#閾値計算アルゴリズム) を参照してください。  
    - 上記例では、「アップロード間隔5秒」「JSONファイルサイズ16KB」を目標とした設定になっています。

---

## レスポンス仕様

### 成功時

```json
{
    "success": true,
    "result": {
        "god": "02cf751b15ce7de09d29aa612a48788b7ce576ba513a50c666404131d2988f5718",
        "server_name": null,
        "pow_algorithm": ["tlsln",[5,16,0.8]]
    }
}
```

### フィールド説明（リスト形式）

- **success**  
    処理結果。`true`の場合は成功、`false`の場合は失敗です。

- **result**  
    処理結果の詳細情報を格納するオブジェクトです。

    - **god**  
        登録された管理者の公開鍵です。  
        管理者機能を利用する際に必要です。

    - **server_name**  
        登録されたサーバー名です。

    - **pow_algorithm**  
        設定されたPoWアルゴリズムです。

---

### 失敗時

エラーが発生した場合は、[`エラーコード`](./errorcodes.md) を参照してください。

