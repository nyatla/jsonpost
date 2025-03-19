<?php


namespace Jsonpost\responsebuilder;

use Exception;
use Throwable;

class ErrorResponseBuilder extends Exception implements IResponseBuilder  {
    private readonly int $status;
    private readonly int $err_code;
    private readonly ?array $hint;
    private static array $errorMessages = [
        0   => 'Internal Error.',

        101   => 'Request not allowed.',
        102   => 'URL parameter structure is invalid.',
        103   => 'URL parameter value is invalid.',

        201=>'This request requires a PowStamp.',
        202=>'PowStamp format is invalid.',
        203=>'ECDSA signature is incorrect.',
        204=>'Nonce is invalid.',
        205=>'PoW calculation value is below the required threshold.',
        206=>'You are not god.',
        207=>'Cannot register a new account.',
    
        301=>'Request body format is invalid (cannot be read or is missing).',
        302=>'Request body is missing required values (insufficient content).',
        303=>'Request body contains invalid values (incorrect content).',
        304=>'Request body is too large.',

        401=>'The requested record was not found.',

        501=>'The system has already been initialized.',
        502=>'Implementation error.'
    ];

    
    
    


    /**
     * @param int $err_code
     * @param string $message
     * @param int $status
     * @param array $hint
     * @throws \Jsonpost\responsebuilder\ErrorResponseBuilder
     * @return never
     */
    public static function throwResponse(int $err_code,string $message=null, int $status = 400,array $hint=null):IResponseBuilder{
        if(!array_key_exists($err_code,ErrorResponseBuilder::$errorMessages)){
            $message="Internal error. unknown error code $err_code not implemented.";
            $status = 500;
            $err_code = 0;
        }
        throw new ErrorResponseBuilder($err_code, $message,$status, $hint);
    }
    public static function catchException(Throwable $e):IResponseBuilder{
        return new ErrorResponseBuilder(0,$e->getMessage(),500);
    }
    
    private function __construct(int $err_code,string $message=null, int $status = 400,array $hint=null) {
        parent::__construct($message);
        $this->status = $status;
        $this->hint = $hint;
        $this->err_code = $err_code;
    }


    public function sendResponse() {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST");
        header("Access-Control-Allow-Headers: Content-Disposition, Content-Type, Content-Length, Accept-Encoding");
        header('Content-Type: application/json');
        http_response_code($this->status);

        echo json_encode([
            'success' => false,
            'error'=>[
                'code'=> $this->err_code,
                'message' => $this->message?$this->message:ErrorResponseBuilder::$errorMessages[$this->err_code],
                'hint'=>$this->hint
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    
}
