<?php

require '../../vendor/autoload.php';

use Opis\JsonSchema\{
    Validator,
    ValidationResult,
    Errors\ErrorFormatter,
};

$schema = <<<'JSON'
{
    "type": "object",
    "properties": {
        "name": {
            "type": "string",
            "minLength": 2
        },
        "email": {
            "type": "string",
            "format": "email"
        }
    }}
JSON;

// assuming data is coming from $_POST
// you can also use $_GET, or a custom array
$data =json_decode('{"name":"aaa","email":"anyatla.jp"}');

// Create a new validator
$validator = new Validator();
$validator->setMaxErrors(1);
$validator->setStopAtFirstError(true);

/** @var ValidationResult $result */
$result = $validator->validate($data, $schema);

if ($result->isValid()) {
    echo "Valid";
} else {
    // Print errors
    print_r((new ErrorFormatter())->formatFlat($result->error()));
}
