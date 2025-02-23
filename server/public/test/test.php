<?php


require dirname(__FILE__) .'/../../vendor/autoload.php'; // Composerでインストールしたライブラリを読み込む
use JsonPath\JsonPath;
use JsonPath\JsonPathException;

$json = <<<JSON
{ "store": {
    "book": [ 
      { "category": "reference",
        "author": "Nigel Rees",
        "title": "Sayings of the Century",
        "price": 8.95
      },
      { "category": "fiction",
        "author": "Evelyn Waugh",
        "title": "Sword of Honour",
        "price": 12.99
      },
      { "category": "fiction",
        "author": "Herman Melville",
        "title": "Moby Dick",
        "isbn": "0-553-21311-3",
        "price": 8.99
      },
      { "category": "fiction",
        "author": "J. R. R. Tolkien",
        "title": "The Lord of the Rings",
        "isbn": "0-395-19395-8",
        "price": 22.99
      }
    ],
    "bicycle": {
      "color": "red",
      "price": 19.95
    }
  }
}
JSON;

$data = json_decode($json, true);

$jsonPath = new JsonPath();

echo "Example 1: - The authors of all books in the store: \n";
echo json_encode($jsonPath->find($data, "$.store.book[*].author"), JSON_PRETTY_PRINT);
echo "\n\n";
#$v="c2501be45d583a3055f417594dee8af10dff5d4bd2733e7eaa1ac9fe165ea26d33efd9d0fd9898f868edff521e7f9fe8fa0a69ffc145d161f20b14f1553fcb2903af5bf52aa42e6f23b4b30c1ee4e08dff72ea05e59c8d873014d8ad1f45f949d22f47c2d100000022";
#print(hash('sha256', hash('sha256', hex2bin($v), true), false));


