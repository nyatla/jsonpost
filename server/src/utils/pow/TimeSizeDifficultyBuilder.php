<?php
namespace Jsonpost\utils\pow;
use Exception;
use Jsonpost\responsebuilder\ErrorResponseBuilder;


class TimeLogiticsSizeLnDifficulty implements ITimeSizeDifficultyProvider{
    public const NAME="tlsln";

    private LogNormalRateProvider $g;
    private LogiticsRateProvider $l;
    private array $_params;

    public function __construct(float $time_target, float $size_peak_point, float $size_share) {
        $this->g = new LogNormalRateProvider($size_peak_point, $size_share);
        $this->l = new LogiticsRateProvider($time_target*0.5);
        $this->_params=[$time_target,$size_peak_point,$size_share];
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

    public function pack(): mixed {
        return [TimeLogiticsSizeLnDifficulty::NAME,$this->_params];
    }
}


class TimeSizeDifficultyBuilder{
    /**
     * ['name',[params]]形式の値をパースする
     */
    public static function fromText(string $input): ITimeSizeDifficultyProvider {    
        return self::fromJson(json_decode($input));
    }
    public static function fromJson(mixed $input): ITimeSizeDifficultyProvider {     
        switch($input[0]){
        case TimeLogiticsSizeLnDifficulty::NAME:
            return new TimeLogiticsSizeLnDifficulty(
                $input[1][0],$input[1][1],$input[1][2]
            );
        }
        throw ErrorResponseBuilder::throwResponse(303,"Invalid algoritm {$input}");
    }
}
