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
        "total": 7,
        "range": {
            "offset": 3,
            "limit": 3
        },
        "matched":1
    }
}
```

### フィールド説明

- **success**  
    処理結果。`true`の場合は成功、`false`の場合は失敗です。

- **result**  
    結果を格納します。
    - **total**  
        レコードの総数です。
    - **range**
        探索範囲を示します。
    - **matched**
        探索範囲内での一致数を返します。

---

### 失敗時

エラーが発生した場合は、[`エラーコード`](./errorcodes.md) を参照してください。