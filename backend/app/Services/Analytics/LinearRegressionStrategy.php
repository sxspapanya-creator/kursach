<?php

namespace App\Services\Analytics;
class LinearRegressionStrategy extends BaseForecastStrategy
{
    public function forecast(array $data, int $steps): array
    {
        $n = count($data);
        if ($n < 2) {
            return (new SimpleExtrapolationStrategy())->forecast($data, $steps);
        }

        $x = range(0, $n - 1);
        $y = $data;

        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = array_sum(array_map(fn($xi, $yi) => $xi * $yi, $x, $y));
        $sumX2 = array_sum(array_map(fn($xi) => $xi * $xi, $x));

        $denominator = ($n * $sumX2 - $sumX * $sumX);
        if ($denominator == 0) {
            return (new SimpleExtrapolationStrategy())->forecast($data, $steps);
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;
        $intercept = ($sumY - $slope * $sumX) / $n;

        $forecast = [];
        $lastValue = $data[$n - 1];
        for ($k = 1; $k <= $steps; $k++) {
            $nextX = $n - 1 + $k;
            $value = $slope * $nextX + $intercept;
            $value = max($lastValue * 0.3, min($lastValue * 2.0, $value));
            $forecast[] = round(max(0, $value), 2);
        }
        return $forecast;
    }

    public function getName(): string
    {
        return 'LinearRegression';
    }

    public function getReliabilityMessage(): string
    {
        return '📊 Прогноз имеет хорошую точность. Для улучшения рекомендуется накопить 15+ месяцев данных.';
    }

    public function getMinMonths(): int
    {
        return 7;
    }

    public function getMaxMonths(): ?int
    {
        return 14;
    }
}