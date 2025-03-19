# JsonSpecs


標準規格はJSONの基本構造を定める。
基本構造は```version```を含み、他の要素は形式により異なる。

versionが存在しない場合は、受信側のシステムが想定した形式であると仮定して処理してよい。


```
{
    "version":バージョン番号,
    ...
}
```
versionには、urn:ドメイン:受け取り側システム名:機能名:版番号を指定する。


# 汎用系

## urn::nyatla.jp:json-result::error
エラーコードを通知する。
```
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "type": "object",
  "properties": {
    "version": {
      "type": "string",
      "pattern": "^urn::nyatla.jp:json-result::error$"
    },
    "success": {
      "type": "string",
      "enum": ["false"]
    },
    "message": {
      "type": "string"
    }
  },
  "required": ["version", "success"],
  "additionalProperties": false
}
```


## urn::nyatla.jp:json-request::ecdas-signed-upload:1
jsonペイロードを署名とともにリクエストするためのエンベロープである。

```
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "type": "object",
  "properties": {
    "version": {
      "type": "string",
      "pattern": "^urn::nyatla.jp:json-request::ecdas-signed-upload:1$"
    },
    "signature": {
      "type": "string",
      "pattern": "^[0-9a-fA-F]{64}$"
    },
    "data": {
      "type": "object"
    }
  },
  "required": ["version", "signature", "data"],
  "additionalProperties": false
}
```







## urn::nyatla.jp:json-result:*:error
{

}


## urn::nyatla.jp:llm-token-rate-benchmark:ollama:2

ollamaのベンチマーク格納形式

署名

nonce=uint32.hex()
署名=hash(urand(16).hex()+nonce.hex())

検証文字列
nonce[8]+署名[128]



## urn::nyatla.jp:json-response:llm-token-rate-benchmark:ecdas-signed-upload:1
```
{
    "version":"urn::nyatla.jp:json-response:llm-token-rate-benchmark:ecdas-signed-upload:1",
    "message":""
}
```



## urn::nyatla.jp:json-response:error:1
```
{
    "success":false,
    "message":""
}
```

アップロード結果

