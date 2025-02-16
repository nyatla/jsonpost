<?php
/*  各種設定


*/

namespace Jsonpost{
    use \PDO as PDO;
    class Config{
        public const VERSION="nyatla.jp:jsonpost:1";
        //データベースファイルの場所
        public const DB_PATH="../../db/test.sqlite3";
        //JSONの最大サイズ
        public const MAX_JSON_SIZE=256*1024;
        //
        public const ENABLE=true;   
        static function getRootDb(): PDO{
            $fpath=dirname(__FILE__).Config::DB_PATH;
            $db = new PDO("sqlite:$fpath");
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $db->setAttribute(PDO::ATTR_TIMEOUT, 10);
            return $db;
        }
    
    }

    //Configによる処刑
    if(Config::ENABLE!=true){
        die();
    }     

}


?>