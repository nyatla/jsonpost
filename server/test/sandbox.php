<?php
function parseTlsln(string $input): ?array {
    if (preg_match('/^\s*(\w+)\s*\(\s*(-?\d*\.?\d+)\s*,\s*(\d+)\s*,\s*(-?\d*\.?\d+)\s*\)\s*$/', $input, $matches)) {
        return [
            'name' => $matches[1],  // 関数名を取得
            'et' => (float)$matches[2],
            's' => (int)$matches[3],
            's_sigma' => (float)$matches[4]
        ];
    }
    return null;
}

// テストコード
$testCases = [
    'tlsln(1.5,32,.5)',       // 標準ケース
    'tlsln( 10 , 100 , 2.0 )', // 空白が入ったケース
    ' tlsln(0.8,50,0.3)',     // 前後に空白
    'invalid(1.5,32,.5)',     // 関数名が違うケース
    'testFunc(-3.2, 42, 1.1)', // 負の値を含む
    'abc(2.7, 99, -0.8)',     // 別の関数名、負の s_sigma
    'tlsln(abc, 32, .5)',     // 無効な数値
];

foreach ($testCases as $test) {
    $result = parseTlsln($test);
    echo "Input: $test\n";
    echo "Output: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
}
