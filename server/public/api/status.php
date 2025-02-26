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
use Jsonpost\utils\{
    ApplicationInstance,
};


$db = Config::getRootDb();//new PDO('sqlite:benchmark_data.db');
$db->exec('BEGIN IMMEDIATE');

try{
    //前処理
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        (new ErrorResponseBuilder("Method Not Allowed",405))->sendResponse();
    }
    $app=new ApplicationInstance($db);
    $properties=$app->properties_records;

    $r=[
        'welcome'=>[
            'version'=>$properties[PropertiesTable::VNAME_VERSION],
            'server-name'=>$properties[PropertiesTable::VNAME_SERVER_NAME],
            // 'difficulty'=>$app->update(null)[1],
        ],
        'operation'=>[
        ],    
    ];
    $db->exec("COMMIT");    
    (new SuccessResultResponseBuilder($r))->sendResponse();
}catch(ErrorResponseBuilder $exception){
    $db->exec("ROLLBACK");
    $exception->sendResponse();
}catch(Exception $e){
    $db->exec("ROLLBACK");    
    (new ErrorResponseBuilder($e->getMessage()))->sendResponse();
    // (new ErrorResponseBuilder("Internal Error"))->sendResponse();
}catch(Error $e){
    $db->exec("ROLLBACK");    
    (new ErrorResponseBuilder($e->getMessage()))->sendResponse();
    // (new ErrorResponseBuilder('Internal Error.'))->sendResponse();
}

