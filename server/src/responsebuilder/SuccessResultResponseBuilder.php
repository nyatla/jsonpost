<?php


namespace Jsonpost\responsebuilder;

class SuccessResultResponseBuilder implements IResponseBuilder {
    private $result;
    private $flags;
    public function __construct($result,int $flags=0) {
        $this->result=$result;
        $this->flags=$flags;
    }

    public function sendResponse() {
        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'result'=> $this->result
        ], JSON_UNESCAPED_UNICODE|$this->flags);
    }
}