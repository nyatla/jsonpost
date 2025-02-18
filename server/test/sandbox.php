
<?
// $privateKey = '59a94c947057c92a4ebd2b6f85d90ff3a862e2bbc8df1a1eb0f68dee69634af9'; // 32バイトの秘密鍵（16進数）
// $signer = new EcdsaSignerLite($privateKey);

// $message = "Hello, world!";
// $signature = $signer->sign($message);
// $publicKey = $signer->getPublicKey();

// $isValid = EcdsaSignerLite::verify($signature, $publicKey, "Hello, world!");
// print_r(">>$isValid");
// if ($isValid) {
//     echo "✅ 署名の検証に成功しました";
// } else {
//     echo "❌ 署名の検証に失敗しました";
// }



// function getPublicKeyFromCompressedHex(string $compressedHex) {
//     $ec = new EC('secp256k1');

//     // hex 文字列をバイナリに変換
//     $compressedKey = $compressedHex;
//     // if (!$compressedKey || (strlen($compressedKey) !== 33)) {
//     //     throw new InvalidArgumentException("Invalid compressed public key format");
//     // }

//     // 公開鍵インスタンスを生成
//     return $ec->keyFromPublic($compressedKey, 'hex');
// }

// // --- テスト ---
// $compressedHex = "032bd175671677f87729f927d72fd8da8cc6cad05af1d2b8b14e97b31888f9dff7"; // 33-byte compressed key
// $publicKey = getPublicKeyFromCompressedHex($compressedHex);

// echo "Public Key X: " . $publicKey->getPublic('hex') . PHP_EOL;




// $e=new EcdsaSignerLite(privateKey: "59a94c947057c92a4ebd2b6f85d90ff3a862e2bbc8df1a1eb0f68dee69634af9");
// print_r("pub:{$e->getPublicKey()}.<br/>");
// [$p,$n]=EasyEcdsaStreamBuilderLite::decode("5fde0a19900e5e3e943f501fdaa36e49ef53a3e2d31b0c58db6c5b636a1f0269d2c0b9341eddac35afe88e249b211ae8e50cb35cfa52be56d2b0472ba3e050a803f03ce7b379a0472534fb2a7c5c9b69008d0b02a77cf922a9aa59a98c9381eeed2f43a3f2");
// print_r($n."<br/>");
// print_r($p);
?>