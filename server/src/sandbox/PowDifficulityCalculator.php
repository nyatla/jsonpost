<?php
namespace Jsonpost\utils;
// use JsonPost;
use Jsonpost\Config;



class PowDifficulityCalculator {
    /**
     * Powの動的難易度計算器
     * 一定時間に受理する nonce の数を一定に保つように調整しながら、
     * 入力された nonce が閾値を超えたか確認する。
     */
    private int $threshold;
    private int $lastTimeLac = 0; // 最終計算時刻（ミリ秒）
    private float $acceptPerSec = Config::NEW_ACCOUNT_PER_SEC; // 受理する nonce の目標レート（1秒あたり）

    public function __construct(int $startThreshold,int $lastTimeLac) {
        $this->threshold = $startThreshold;
        $this->lastTimeLac = $lastTimeLac;
    }

    /**
     * 現在の難易度を取得（32bit空間における log2 スケール）
     */
    public function getDifficulty(): float {
        return max(0,32 - log($this->threshold, 2));
    }
    public function getThreshold():float{
        return $this->threshold;
    }
    public function getLastTimeLac(): int {
        return $this->lastTimeLac;
    }

    /**
     * nonce32 をチェックし、状態を更新する。
     * nonce32 が閾値以下であれば true を返す。
     */
    public function update(?int $nonce32): bool {
        $now = round(microtime(true) * 1000); // 現在の時刻（ミリ秒）
        $ep = $now - $this->lastTimeLac; // 経過時間（ミリ秒）
        $th = $this->threshold;
        $ret = false;

        if ($nonce32 === null) {
            if ($ep > (1000 * 3 / $this->acceptPerSec)) {
                $th *= 2;
            } elseif ($ep < (1000 / 3 / $this->acceptPerSec)) {
                // 60%を切っているなら閾値が高すぎるので下げる
                $th /= 2;
            }
        } elseif ($nonce32 <= $th) {
            $this->lastTimeLac = $now;
            $ret = true;

            if ($ep > (1000 * 3 / $this->acceptPerSec)) {
                $th *= 2;
            } elseif ($ep < (1000 * 2 / 3 / $this->acceptPerSec)) {
                // 60%を切っているなら閾値が高すぎるので下げる
                $th /= 2;
            } else {
                // 適正範囲内だったら微調整
                $th *= $ep / (1000 * $this->acceptPerSec);
            }
        }

        $this->threshold = round($th + 1);
        return $ret;
    }
}