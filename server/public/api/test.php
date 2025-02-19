<?php
require dirname(__FILE__) .'/../../vendor/autoload.php'; // Composerでインストールしたライブラリを読み込む
// use Jsonpost\utils\ecdsasigner\{EcdsaSignerLite,};

// $pk="b79678e0d98bb60d0727709a54359a7d7cbb17a7d618f5c19d851245ca5adc5c";

// $esl=new EcdsaSignerLite(privateKey: $pk);
// $b=$esl->getPublicKey();
// print_r($pk.'<br/>');
// print_r($b.'<br/>');
// print_r($esl->sign("123").'<br/>');
// $b=$esl::verify($esl->sign("123"),$b,"113");

// print_r($esl::recover($esl->sign("123"),"123",1));

// // print_r(EcdsaSignerLite::compressKey($b).'<br/>');

// use Jsonpost\{Config};
// use Jsonpost\db\tables\nst2024\PropertiesTable;

// // SQLiteデータベースに接続




#$v="c2501be45d583a3055f417594dee8af10dff5d4bd2733e7eaa1ac9fe165ea26d33efd9d0fd9898f868edff521e7f9fe8fa0a69ffc145d161f20b14f1553fcb2903af5bf52aa42e6f23b4b30c1ee4e08dff72ea05e59c8d873014d8ad1f45f949d22f47c2d100000022";
#print(hash('sha256', hash('sha256', hex2bin($v), true), false));


