<?php
/* Nyatla.jp標準規格テーブル
*/
namespace Jsonpost\db\tables\nst2024;

use Jsonpost\utils\pow\ITimeSizeDifficultyProvider;
use Jsonpost\utils\pow\TimeSizeDifficultyBuilder;


class PropertiesRows {
    public string $version;
    public string $god;
    public bool $welcome;

    public ?string $json_schema;
    public bool $json_jcs;

    /**
     * jsonオブジェクト
     * @var object
     */
    public ITimeSizeDifficultyProvider $pow_algorithm;
    public ?string $server_name;
    public int $root_pow_accept_time;

    public function __construct(array $data) {
        $a=[];
        foreach($data as $k){
            $a[$k[0]]=$k[1];
        } 

        $this->version = $a[PropertiesTable::VNAME_VERSION];
        $this->god = $a[PropertiesTable::VNAME_GOD];
        $this->pow_algorithm =TimeSizeDifficultyBuilder::fromText($a[PropertiesTable::VNAME_POW_ALGORITHM]);
        $this->server_name = $a[PropertiesTable::VNAME_SERVER_NAME];
        $this->root_pow_accept_time =  (int)$a[PropertiesTable::VNAME_ROOT_POW_ACCEPT_TIME];
        $this->welcome =  ((int)$a[PropertiesTable::VNAME_WELCOME])>0?true:false;
        $this->json_schema =  $a[PropertiesTable::VNAME_JSON_SCHEMA];
        $this->json_jcs =  ((int)$a[PropertiesTable::VNAME_JSON_JCS])>0?true:false;
    }
}