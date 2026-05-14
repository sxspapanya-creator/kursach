<?php

namespace App\Services\Analytics;

class SimpleExtrapolationStrategy extends BaseForecastStrategy
{
    public function forecast(array $data, int $steps): array
    {
        $n = count($data);
        if ($n < 2) {
            $lastValue = $data[$n - 1] ?? 0;
            return array_fill(0, $steps, $lastValue);
        }

        $lastValue = $data[$n - 1];
        $firstValue = $data[0];
        $avgChange = ($lastValue - $firstValue) / ($n - 1);

        $maxDecline = $lastValue * 0.15;
        $maxGrowth = $lastValue * 0.20;
        $avgChange = max(-$maxDecline, min($maxGrowth, $avgChange));

        $forecast = [];
        for ($i = 1; $i <= $steps; $i++) {
            $value = $lastValue + $avgChange * $i;
            $value = max($lastValue * 0.3, $value);
            $forecast[] = round($value, 2);
        }
        return $forecast;
    }

    public function getName(): string
    {
        return 'SimpleExtrapolation';
    }

    public function getReliabilityMessage(): string
    {
        return '⚠️ Прогноз на основе минимальных данных (3-6 месяцев). Рекомендуется накопить больше истории.';
    }

    public function getMinMonths(): int
    {
        return 3;
    }

    public function getMaxMonths(): ?int
    {
        return 6;
    }
}