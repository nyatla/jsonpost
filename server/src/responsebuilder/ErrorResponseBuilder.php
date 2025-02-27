<?php


namespace Jsonpost\responsebuilder;

use \Exception as Exception;

class ErrorResponseBuilder extends Exception implements IResponseBuilder  {
    private readonly int $status;
    private readonly int $err_code;
    private readonly array $hint;
    
    public function __construct(string $message, int $status = 400,int $err_code=0,array $hint=null) {
        parent::__construct($message);
        $this->status = $status;
        $this->err_code = $err_code;
        $this->hint = $hint;
    }

    public function sendResponse() {
        header('Content-Type: application/json');
        http_response_code($this->status);
        
        echo json_encode([
            'success' => false,
            'error'=>[
                'code'=> $this->err_code,
                'message' => $this->message,
                'hint'=>$this->hint
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
