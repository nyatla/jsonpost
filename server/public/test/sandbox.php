<?php


$r=[1,2];

class a{
    public readonly bool $isinsert;
    public readonly array $record;
    public function __construct(bool $isinsert,array $record) {
        $this->isinsert=$isinsert;
        $this->record=$record;
    }    
};
$a=new a(True,$r);
// print_r($a);

