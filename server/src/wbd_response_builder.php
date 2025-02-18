<?php


// namespace Jsonpost{
//     use \Exception as Exception;
//     interface IResponseBuilder{
//         public function sendResponse();
//     }
//     class ErrorResponseBuilder extends Exception implements IResponseBuilder  {
//         private int $status;
        
//         public function __construct(string $message, int $status = 400) {
//             parent::__construct($message);
//             $this->status = $status;
//         }

//         public function sendResponse() {
//             header('Content-Type: application/json');
//             http_response_code($this->status);
            
//             echo json_encode([
//                 'success' => false,
//                 'message' => $this->message
//             ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
//         }
//     }
// }

// ?>