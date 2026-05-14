<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Contracts\ForecastMethodInterface;
abstract class BaseForecastStrategy implements ForecastMethodInterface
{
    protected function constrainForecast(array $forecast, float $lastValue): array
    {
        $minValue = $lastValue * 0.3;
        $maxValue = $lastValue * 2.0;
        return array_map(fn($v) => max($minValue, min($maxValue, $v)), $forecast);
    }

    protected function smoothForecast(array $forecast): array
    {
        $smoothed = [];
        $window = 3;
        $count = count($forecast);

        for ($i = 0; $i < $count; $i++) {
            $sum = 0;
            $windowCount = 0;
            for ($j = -$window; $j <= $window; $j++) {
                $idx = $i + $j;
                if ($idx >= 0 && $idx < $count) {
                    $sum += $forecast[$idx];
                    $windowCount++;
                }
            }
            $smoothed[] = $sum / $windowCount;
        }
        return $smoothed;
    }
}