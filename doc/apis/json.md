# /json API仕様

`/json.php` は、JSONドキュメントを参照するためのAPIです。

## リクエストパラメータ

- **uuid**  
    取得対象のドキュメントを識別するUUIDです。指定必須です。

- **raw**  
    このパラメータを指定すると、レスポンスがJSON本体のみになります。

- **path**  
    対象ドキュメントから取得するデータを指定するためのJSONPathです。  
    phpのjsonpath拡張モジュールに基づき処理されます。  
    （SQLiteのJSON1拡張よりも幅広い式が使用可能です）  
    詳細は [FlowCommunications/JSONPath](https://github.com/FlowCommunications/JSONPath) を参照してください。

---

## リクエスト形式

```http
GET /json.php?uuid=01956156-6566-702c-9f81-7b05a19f0e89
```

---

## レスポンス仕様

### 成功時

```json
{
  "success": true,
  "result": {
    "path": "$",
    "timestamp": 1741094806608,
    "powstamp": "7f9fda7a161898fa48c48244c0e1d7c1e3a359332558071741379f138bcc0b51d12d230e0f7203eb29ea006fee35e32753f25a3b390a6b3721254e0e1ada42c703edd86f79bd656847f74ad5071e2b6c59d5aaa57c7ca9391a6559bee2a97a04df2f5c2e090000008e",
    "uuid_account": "01956156-0db1-73ce-83a4-2f2b223848aa",
    "uuid_document": "01956156-6566-702c-9f81-7b05a19f0e89",
    "json": {
      "key2": "aaa0"
    }
  }
}
```

### フィールド説明

- **success**  
    処理結果。`true`の場合は成功、`false`の場合は失敗です。

- **result**  
    取得したドキュメントの情報を格納するオブジェクトです。

    - **uuid**  
        取得対象ドキュメントのUUIDです。

    - **json**  
        取得したJSONドキュメント本体です。

---

### 失敗時

エラーが発生した場合は、[`エラーコード`](./errorcodes.md) を参照してください。

