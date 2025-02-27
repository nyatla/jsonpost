<?php
function parseTlsln(string $input): ?array {
    if (preg_match('/^\s*(\w+)\(\s*(\d+(\.\d+)?)\s*,\s*(\d+)\s*,\s*(\d+(\.\d+)?)\s*\)\s*$/', $input, $matches)) {
        return [$matches[1], $matches[2], $matches[3], $matches[4]];
    }
    throw new Exception("Invalid format {$input}");
}


function test_fromText() {
    $testCases = [
        // ✅ 正しい形式（整数 & 小数）
        "func(1.5,32,0.5)"    => true,
        "func(10,100,2.0)"    => true,
        "func(0.8,50,0.3)"    => true,
        "func(.5,20,.1)"      => true,  // 小数点のみの表記もOK
        "func(42,99,3.14)"    => true,  // 整数 + 小数の組み合わせ

        // ❌ 負の数（エラーが発生する）
        "func(-1.5,32,0.5)"   => false,
        "func(1.5,-32,0.5)"   => false,
        "func(1.5,32,-0.5)"   => false,

        // ❌ 無効な入力
        "func(abc,32,0.5)"    => false,  // 1つ目が数値でない
        "func(1.5,32,)"       => false,  // 3つ目が空
        "func(1.5, 32)"       => false,  // 引数が足りない
        "func(1.5,32.1,0.5,7)"  => false,  // 引数が多すぎる
        "func(1.5, 32, 0.5 "  => false,  // 閉じ括弧なし
        "func 1.5,32,0.5)"    => false,  // 括弧なし
        "func()"              => false,  // 引数なし
    ];

    foreach ($testCases as $input => $expected) {
        try {
            $result = parseTlsln($input);
            if ($expected) {
                echo "✅ OK: $input\n";
            } else {
                echo "❌ NG: $input (Expected failure but succeeded)\n";
            }
        } catch (Exception $e) {
            if (!$expected) {
                echo "✅ Expected Error: $input\n";
            } else {
                echo "❌ Unexpected Error: $input\n";
            }
        }
    }
}

// テスト実行
test_fromText();
