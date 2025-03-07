<?php

require '../../vendor/autoload.php';

use \Jsonpost\utils\JCSValidator;



function runTests() {
    $validator = new JCSValidator();

    $passCases = [
        '[true,false,null]',
        '{"a":true,"b":false,"c":null}',
        '{"age":30,"name":"Alice"}',
        '{"address":{"city":"New York","state":"NY"},"name":"Alice"}',
        '["apple","banana","cherry"]',
        '[{"age":30,"name":"Alice"},{"age":25,"name":"Bob"}]',
        '{}',
        '[]',
        '{"location":{"city":"New York","country":"USA"},"person":{"age":30,"name":"Alice"}}',
        '[[1,2e3,+3.0],[-4.1e5,5,6],[7,8,9,.5,5.e+1,.5e-3,1.111e0]]',
        '{"key":"value with \\n newline"}',
        '{"key":"value with \\t tab"}',
        '{"key":"value with \\" quote"}',
        '{"key":"backslash \\\"}',
        '{"key":"unicode \u0041"}',
        '{"key":"multiple escapes \\" \\\\ \\n \\t \\b \\f \\r"}',
        '[0,1,-1,1.0,-1.0,1e10,1E-10,-1.23456789,0.5,.5,5.]',
        '[12345678901234567890]',
        '[1e-1,1e+1,1E-1,1E+1]',
        '{"a":{"b":[1,2,3],"c":true},"d":[false,null,3.14]}',
        '[{"nested":{"key":"value"}},["array in array"],{"k":1}]'
    ];

    $failCases = [
        '{"a":1,}',        
        '1',
        'true',
        '"Hello, World!"',
        '"Hello \\xWorld"',
        '"Hello \\"World\\""',
        '[true,false,null ]',
        '["a":true,"b":false,"c":null]',
        '{ }',
        '[ ]',
        '[, ]',
        '{"z": 1, "a": 2}', // key order violation
        '[5.+e1]',
        '{"a":1,"a":2}', // duplicate key (also covered by order check)
        '{"person":{"name":"Alice","age":30},"location":{"city":"New York","country":"USA"}}',
        '[{"name":"Alice","age":30},{"name":"Bob","age":25}]',
        '{"name":"Alice","age":30}',
        '{"age":30"name":"Alice"}',
        '{"name":"Alice","address":{"city":"New York","state":"NY"}}',
        '"Hello, World!',
        '{: "value"}',
        '{123: "value"}',
        '["apple" "banana"]',
        '[{"name": "Alice", "age": "thirty"}, {"name": "Bob", "age": "twenty"}]',
        '{"name" "Alice"}',
        '["apple", "banana",]',
        '{"name": "Alice", "age": 30,}',
        '[{"name": "Alice"}, "banana"]',
        '[1e]',
        '[1e+]',
        '[1e-]',
        '[.e1]',
        '[1,2,3,]', // trailing comma
        '{"a":1,"b":2,}', // trailing comma
        'true', // top-level scalar
        'null', // top-level scalar
        '"string"', // top-level scalar
        '123', // top-level scalar
        '{"b":1,"a":2}' // invalid key order
    ];

    echo "=== PASS CASES (Expected to pass) ===\n";
    foreach ($passCases as $json) {
        try {
            $validator->isJcsToken($json);
            echo "PASS: $json\n";
        } catch (Exception $e) {
            echo "FAIL (Unexpected Failure): $json => {$e->getMessage()}\n";
        }
    }

    echo "\n=== FAIL CASES (Expected to fail) ===\n";
    foreach ($failCases as $json) {
        try {
            $validator->isJcsToken($json);
            echo "FAIL (Should have failed): $json\n";
        } catch (Exception $e) {
            echo "PASS (Expected Failure): $json => {$e->getMessage()}\n";
        }
    }
}

runTests();