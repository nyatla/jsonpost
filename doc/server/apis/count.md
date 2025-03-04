# /count API仕様

`/count.php` は、JSONドキュメントの統計情報を取得するためのAPIです。
ページネーションで指定した範囲にあるドキュメントの統計を返します。

## リクエストパラメータ

このAPIのパラメータは、[`/list.php`](./list_api_spec.md)と同一です。

---

## リクエスト形式

```http
GET /count.php
```

---

## レスポンス仕様

### 成功時

```json
{
    "success": true,
    "result": {
        "matched": 24,
        "total": 24
    }
}
```

### フィールド説明

- **success**  
    処理結果。`true`の場合は成功、`false`の場合は失敗です。

- **result**  
    統計情報を格納するオブジェクトです。

    - **matched**  
        条件に一致したドキュメント数です。

    - **total**  
        対象範囲内の全ドキュメント数です。

---

### 失敗時

エラーが発生した場合は、[`エラーコード`](./errorcodes.md) を参照してください。