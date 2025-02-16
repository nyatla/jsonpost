<?php

use Jsonpost\Config;
/**
 * 所定の書式に格納したJSONファイルのアップロードを受け付けます。
 * 
 */
require_once (dirname(__FILE__) ."/../../src/config.php");
require_once (dirname(__FILE__) ."/../../src/response_builder.php");

use Jsonpost\{IResponseBuilder};

class SuccessResponseBuilder implements IResponseBuilder {
    
    public function __construct() {
    }

    public function sendResponse() {
        header('Content-Type: application/json');
        http_response_code(200);
        
        echo json_encode([
            'success' => true,
            'result'=>[
                'version'=>Config::VERSION
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}


(new SuccessResponseBuilder())->sendResponse();

