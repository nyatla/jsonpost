# /upload API仕様

`/upload` は、サーバーにJSONデータを登録するためのAPIです。
このAPIの実行には、HTTPヘッダーに[`PowStamp`](../../powstamp.md)-1を指定する必要があります。

サーバーは、リクエストに対して次の検証を行い、すべての検証をパスしたものだけをデータベースに登録します。

- 署名検証を行います。
- アカウントが既存であればNonceの増加を確認します。
- アカウントの操作履歴を取得し、存在しなければ新規に発行します。
- アカウントの固有難易度を計算し、PoWが閾値を超えているか確認します。
- ドキュメントがデータベースに登録済みか確認し、未登録であれば一時登録します。
- ドキュメントとアカウントの識別IDを履歴に一時記録します。
- アカウントの更新情報を一時記録します。
- すべての処理が正常に終了した場合のみ、データベースに書き込みます。

## リクエスト仕様

updateのPowStampは署名機能、Nonce、PowStampスコアの機能を利用します。
リクエストがサーバーに受け入れられるには、サーバーが要求するnonceを持ち、PowStampスコアが閾値を満たすPowStampを作成する必要があります。

ハッシング方式については[ハッシング](../../powstamp.md#ハッシング)を参考にしてください。

### リクエスト形式

```http
POST /upload.php HTTP/1.1
PowStamp-1: <hex値>
Content-Type: application/json

{ JSONデータ }
```

## レスポンス仕様

### 成功時

```json
{
    "success": true,
    "result": {
        "document": {
            "status": "new",
            "json_uuid": "01955010-e2a3-7368-9b3b-e0ef09caf607"
        },
        "account": {
            "status": "new",
            "user_uuid": "01955010-e2b8-718e-942f-5b8d5f7404cb",
            "nonce": 10
        },
        "pow": {
            "domain": "root",
            "required": 127656310,
            "accepted": 98254575
        }
    }
}
```

リクエストが成功すると、必要に応じてuuidが割り当てられ、accountのnonceがPowNonceの値+1に更新されます。  
nonceが`0xffffffff`になると、それ以降の操作はできなくなるため注意してください。

### フィールド説明

- **success**  
    処理結果。`true`の場合は成功、`false`の場合は失敗です。

- **result**  
    処理結果の詳細情報を格納するオブジェクトです。

    - **document**  
        登録されたドキュメントに関する情報を格納します。
        - **status**  
            ドキュメントの状態です。`new`は新規登録、`copy`は複写を示します。
        - **json_uuid**  
            登録されたドキュメントのUUIDです。

    - **account**  
        操作を行ったアカウントに関する情報を格納します。
        - **status**  
            アカウントの状態です。`new`は新規作成、`exist`は既存のアカウントを示します。
        - **user_uuid**  
            操作を行ったユーザーのUUIDです。
        - **nonce**  
            処理完了後の最新のNonce値です。

    - **pow**  
        PoW検証に関する情報を格納します。
        - **domain**  
            PoWスコアの評価ドメインです。`root`と`account`があります。
        - **required**  
            サーバーが要求したPoWスコア（閾値）です。
        - **accepted**  
            実際に受け入れられたPoWスコアです。

---

### 失敗時

204,205エラーの場合は、`hint`を参考にPowStampを再計算してください。  
詳細は[エラーコード](errorcodes.md#apiエラーコード一覧)を参照してください。

