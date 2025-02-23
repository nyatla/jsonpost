<?php


namespace Jsonpost\responsebuilder;

class SuccessResultResponseBuilder implements IResponseBuilder {
    private $result;
    public function __construct($result) {
        $this->result=$result;
    }

    public function sendResponse() {
        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'result'=> $this->result
        ], JSON_UNESCAPED_UNICODE);
    }
}