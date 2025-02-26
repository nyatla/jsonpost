<?php
namespace Jsonpost\utils\pow;

require dirname(__FILE__) .'/rateprovider.php'; // Composerでインストールしたライブラリを読み込む

interface ITimeSizeDifficultyProvider{
    public function rate(float $time, float $size): float;
    public function serialize(): string;
}



