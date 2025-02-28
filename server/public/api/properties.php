<?php




require dirname(__FILE__) .'/../../vendor/autoload.php'; // Composerでインストールしたライブラリを読み込む


use Jsonpost\Config;



use Jsonpost\responsebuilder\{
    IResponseBuilder,
    ErrorResponseBuilder,
    SuccessResultResponseBuilder
};
use Jsonpost\db\tables\nst2024\{
    PropertiesTable
};


$db = Config::getRootDb();//new PDO('sqlite:benchmark_data.db');

try{
    //前処理
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        (new ErrorResponseBuilder("Method Not Allowed",405))->sendResponse();
    }
    $pt=new PropertiesTable($db);
    $plist=[];
    foreach($pt->selectAll(PDO::FETCH_NUM) as $v) {
        $plist[$v[0]]=$v[1];
    }
    (new SuccessResultResponseBuilder(['properties'=>$plist]))->sendResponse();
}catch(ErrorResponseBuilder $exception){
    $exception->sendResponse();
}catch(Exception $e){
    ErrorResponseBuilder::catchException($e)->sendResponse();
}catch(Error $e){
    ErrorResponseBuilder::catchException($e)->sendResponse();
}




