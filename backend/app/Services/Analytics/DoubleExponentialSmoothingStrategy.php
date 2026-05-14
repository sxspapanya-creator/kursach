<?php

namespace App\Services\Analytics;

class DoubleExponentialSmoothingStrategy extends BaseForecastStrategy
{
    private function optimizeHoltParameters(array $data): array
    {
        $n = count($data);

        if ($n < 18) {
            $alphaRange = [0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8];
            $betaRange = [0.05, 0.1, 0.2, 0.3, 0.4, 0.5];
        } elseif ($n < 22) {
            $alphaRange = [0.2, 0.3, 0.4, 0.5, 0.6];
            $betaRange = [0.05, 0.1, 0.2, 0.3];
        } else {
            $alphaRange = [0.1, 0.2, 0.3, 0.4, 0.5];
            $betaRange = [0.05, 0.1, 0.15, 0.2, 0.25];
        }

        $bestAlpha = 0.3;
        $bestBeta = 0.2;
        $bestError = INF;

        foreach ($alphaRange as $alpha) {
            foreach ($betaRange as $beta) {
                $error = $this->calculateCombinedError($data, $alpha, $beta);
                if ($error < $bestError) {
                    $bestError = $error;
                    $bestAlpha = $alpha;
                    $bestBeta = $beta;
                }
            }
        }
        return [$bestAlpha, $bestBeta];
    }

    private function calculateCombinedError(array $data, float $alpha, float $beta): float
    {
        $n = count($data);
        if ($n < 4) return INF;

        $level = $data[0];
        $trend = $data[1] - $data[0];
        $mapeSum = 0;
        $rmseSum = 0;
        $validCount = 0;

        for ($t = 1; $t < $n; $t++) {
            $oldLevel = $level;
            $predicted = $level + $trend;

            if ($t > $n * 0.7 && $data[$t] > 0) {
                $mapeSum += abs(($data[$t] - $predicted) / $data[$t]);
                $rmseSum += pow($data[$t] - $predicted, 2);
                $validCount++;
            }

            $level = $alpha * $data[$t] + (1 - $alpha) * ($level + $trend);
            $trend = $beta * ($level - $oldLevel) + (1 - $beta) * $trend;
        }

        if ($validCount == 0) return INF;

        $mape = ($mapeSum / $validCount) * 100;
        $rmse = sqrt($rmseSum / $validCount);
        $mean = array_sum($data) / $n;
        $normalizedRmse = $rmse / $mean;

        return ($mape * 0.7) + ($normalizedRmse * 100 * 0.3);
    }

    private function initLevelAndTrend(array $data): array
    {
        $n = count($data);
        $x = range(0, $n - 1);
        $sumX = array_sum($x);
        $sumY = array_sum($data);
        $sumXY = array_sum(array_map(fn($xi, $yi) => $xi * $yi, $x, $data));
        $sumX2 = array_sum(array_map(fn($xi) => $xi * $xi, $x));

        $denominator = ($n * $sumX2 - $sumX * $sumX);
        if ($denominator != 0) {
            $trend = ($n * $sumXY - $sumX * $sumY) / $denominator;
        } else {
            $trend = 0;
        }

        $level = $data[$n - 1] - $trend * ($n - 1);
        $level = max($data[$n - 1] * 0.5, min($data[$n - 1] * 1.5, $level));
        return [$level, $trend];
    }

    public function forecast(array $data, int $steps): array
    {
        $n = count($data);
        if ($n < 4) {
            return (new LinearRegressionStrategy())->forecast($data, $steps);
        }

        list($alpha, $beta) = $this->optimizeHoltParameters($data);
        list($level, $trend) = $this->initLevelAndTrend($data);

        for ($t = 1; $t < $n; $t++) {
            $oldLevel = $level;
            $level = $alpha * $data[$t] + (1 - $alpha) * ($level + $trend);
            $trend = $beta * ($level - $oldLevel) + (1 - $beta) * $trend;
        }

        $forecast = [];
        for ($k = 1; $k <= $steps; $k++) {
            $forecast[] = max(0, $level + $k * $trend);
        }

        $lastValue = $data[$n - 1];
        $forecast = $this->constrainForecast($forecast, $lastValue);
        return $this->smoothForecast($forecast);
    }

    public function getName(): string
    {
        return 'DoubleExponentialSmoothing';
    }

    public function getReliabilityMessage(): string
    {
        return '✅ Прогноз имеет высокую точность.';
    }

    public function getMinMonths(): int
    {
        return 15;
    }

    public function getMaxMonths(): ?int
    {
        return 23;
    }
}