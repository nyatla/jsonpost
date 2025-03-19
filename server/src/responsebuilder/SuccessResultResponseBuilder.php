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
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST");
        header("Access-Control-Allow-Headers: Content-Disposition, Content-Type, Content-Length, Accept-Encoding");
        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'result'=> $this->result
        ], JSON_UNESCAPED_UNICODE|$this->flags);
    }
}