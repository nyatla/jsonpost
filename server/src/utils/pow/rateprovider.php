<?php
namespace Jsonpost\utils\pow;


interface IRateProvider {
    public function rate(float $x): float;
}

class LogiticsRateProvider implements IRateProvider {
    private float $half_x;

    public function __construct(float $half_x) {
        $this->half_x = $half_x;
    }

    public function rate(float $x): float {
        return $x / ($x + $this->half_x);
    }
}

class LogNormalRateProvider implements IRateProvider {
    private float $mu;
    private float $sigma;
    private float $pdf0;

    public function __construct(float $x_max, float $sigma) {
        $this->sigma = $sigma;
        $this->mu = log($x_max) + $sigma ** 2; // mu を x_max から逆算
        $x_peak = $this->peakPosition();
        $this->pdf0 = (1 / ($x_peak * $this->sigma * sqrt(2 * M_PI))) * exp(-((log($x_peak) - $this->mu) ** 2) / (2 * $this->sigma ** 2));
    }

    public function rate(float $x): float {
        if ($x <= 0) {
            return 0; // x が 0 以下の場合、確率密度は 0 とする
        }

        $pdf = (1 / ($x * $this->sigma * sqrt(2 * M_PI))) * exp(-((log($x) - $this->mu) ** 2) / (2 * $this->sigma ** 2));
        return $pdf / $this->pdf0;
    }

    private function peakPosition(): float {
        return exp($this->mu - $this->sigma ** 2);
    }
}