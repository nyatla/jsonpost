<?php
namespace Jsonpost\utils\pow;
use \Exception as Exception;


class TimeLogiticsSizeLnDifficulty implements ITimeSizeDifficultyProvider{
    const NAME="tlsln";

    private LogNormalRateProvider $g;
    private LogiticsRateProvider $l;
    private array $_params;

    public function __construct(float $time_half_target, float $size_peak_point, float $size_share) {
        $this->g = new LogNormalRateProvider($size_peak_point, $size_share);
        $this->l = new LogiticsRateProvider($time_half_target);
        $this->_params=[$time_half_target,$size_peak_point,$size_share];
    }

    /**
     * [1:0]の確率値。1に近いほうが成功。
     * @param float $time
     * @param float $size
     * @return float
     */
    public function rate(float $time, float $size): float {
        return $this->g->rate($size) * $this->l->rate($time);
    }

    public function serialize(): string {
        return TimeLogiticsSizeLnDifficulty::NAME.'('.implode(',',($this->_params)).')';
    }
}


class TimeSizeDifficultyBuilder{
    public static function build(string $algorithm,float $et,float $s,float $s_sigma): ITimeSizeDifficultyProvider{
        switch($algorithm){
        case TimeLogiticsSizeLnDifficulty::NAME:return new TimeLogiticsSizeLnDifficulty($et*.5,$s,$s_sigma);
        }
        throw new Exception("Unknown difficulty {$algorithm}");
    }
    /**
     * name:(n,n,n)形式の値をパースする
     */
    public static function fromText(string $input): ITimeSizeDifficultyProvider {        
        if (preg_match('/^\s*(\w+)\(\s*(\d+(\.\d+)?)\s*,\s*(\d+(\.\d+)?)\s*,\s*(\d+(\.\d+)?)\s*\)\s*$/', $input, $matches)) {
            return TimeSizeDifficultyBuilder::build($matches[1], $matches[2], $matches[4], $matches[6]);
        }
        throw new Exception("Invalid format {$input}");
    }

}
