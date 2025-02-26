<?php
namespace Jsonpost\utils;

use Jsonpost\db\tables\nst2024\{PropertiesTable,DbSpecTable};


/**
 * データベースロックは考慮してないから書込みAPI使うときはちゃんとトランザクションはって
 */
class ApplicationInstance
{
    private PropertiesTable $pt;
    public readonly Array $properties_records;
    public function __construct($db) {
        $pt=new PropertiesTable($db);
        $this->properties_records=$pt->selectAllAsAssoc();
        $this->pt=$pt;
    }
    /**
     * nonceを使って現在のルートdifficulityを更新する。データベースも更新する
     */
    // public function update($nonce32):array
    // {      
    //     $pbc=new PowDifficulityCalculator(
    //         intval($this->properties_records[PropertiesTable::VNAME_ROOT_POW_DIFF_TH]),
    //         intval($this->properties_records[PropertiesTable::VNAME_ROOT_POW_ACCEPT_TIME]));
    //     $ret=$pbc->update($nonce32);
    //     $this->pt->updatePowParams($pbc->getThreshold(),$pbc->getLastTimeLac());

    //     return [$ret,$pbc->getDifficulty()];
    // }
}
