<?php

namespace App\Services\Analytics;

class HoltWintersStrategy extends BaseForecastStrategy
{
    private int $seasonalPeriod = 12;

    private function optimizeParameters(array $data): array
    {
        $bestAlpha = 0.3;
        $bestBeta = 0.2;
        $bestGamma = 0.3;
        $bestMAPE = INF;

        for ($alpha = 0.1; $alpha <= 0.9; $alpha += 0.1) {
            for ($beta = 0.05; $beta <= 0.5; $beta += 0.05) {
                for ($gamma = 0.1; $gamma <= 0.9; $gamma += 0.1) {
                    $mape = $this->calculateMAPE($data, $alpha, $beta, $gamma);
                    if ($mape < $bestMAPE) {
                        $bestMAPE = $mape;
                        $bestAlpha = $alpha;
                        $bestBeta = $beta;
                        $bestGamma = $gamma;
                    }
                }
            }
        }
        return [$bestAlpha, $bestBeta, $bestGamma];
    }

    private function calculateMAPE(array $data, float $alpha, float $beta, float $gamma): float
    {
        $n = count($data);
        if ($n < 12) return INF;

        // Используем ВСЕ данные для расчета, не только последние 30%
        list($level, $trend, $seasonality) = $this->initHoltWinters($data, $alpha, $beta, $gamma);
        $errors = [];
        $validCount = 0;

        // Обучаем на 70% данных
        $trainSize = floor($n * 0.7);

        for ($t = 0; $t < $trainSize; $t++) {
            $oldLevel = $level;
            $level = $alpha * $data[$t] + (1 - $alpha) * ($level + $trend);
            $trend = $beta * ($level - $oldLevel) + (1 - $beta) * $trend;
            $seasonIndex = $t % $this->seasonalPeriod;
            $seasonality[$seasonIndex] = $gamma * ($data[$t] / $level) + (1 - $gamma) * $seasonality[$seasonIndex];
        }

        // Тестируем на оставшихся 30%
        for ($t = $trainSize; $t < $n; $t++) {
            $predicted = ($level + $trend) * $seasonality[$t % $this->seasonalPeriod];
            if ($data[$t] > 0) {
                $errors[] = abs(($data[$t] - $predicted) / $data[$t]);
                $validCount++;
            }
            // Продолжаем обновлять для следующего шага
            $oldLevel = $level;
            $level = $alpha * $data[$t] + (1 - $alpha) * ($level + $trend);
            $trend = $beta * ($level - $oldLevel) + (1 - $beta) * $trend;
            $seasonIndex = $t % $this->seasonalPeriod;
            $seasonality[$seasonIndex] = $gamma * ($data[$t] / $level) + (1 - $gamma) * $seasonality[$seasonIndex];
        }

        return $validCount > 0 ? (array_sum($errors) / $validCount) * 100 : INF;
    }

    private function initHoltWinters(array $data, float $alpha, float $beta, float $gamma): array
    {
        $n = count($data);

        $firstSeason = array_slice($data, 0, $this->seasonalPeriod);
        $level = array_sum($firstSeason) / $this->seasonalPeriod;

        $trend = 0;
        $seasons = floor($n / $this->seasonalPeriod);
        for ($s = 0; $s < $seasons - 1; $s++) {
            $seasonStart = $s * $this->seasonalPeriod;
            $seasonEnd = $seasonStart + $this->seasonalPeriod;
            $seasonAvg = array_sum(array_slice($data, $seasonStart, $this->seasonalPeriod)) / $this->seasonalPeriod;
            $nextSeasonAvg = array_sum(array_slice($data, $seasonEnd, $this->seasonalPeriod)) / $this->seasonalPeriod;
            $trend += ($nextSeasonAvg - $seasonAvg) / $this->seasonalPeriod;
        }
        $trend = $trend / max(1, $seasons - 1);

        $seasonality = [];
        for ($i = 0; $i < $this->seasonalPeriod; $i++) {
            $seasonalSum = 0;
            $seasonCount = 0;
            for ($j = $i; $j < $n; $j += $this->seasonalPeriod) {
                if ($j < $n) {
                    $seasonalSum += $data[$j] / $level;
                    $seasonCount++;
                }
            }
            $seasonality[] = $seasonCount > 0 ? $seasonalSum / $seasonCount : 1.0;
        }

        $seasonalityMean = array_sum($seasonality) / $this->seasonalPeriod;
        if ($seasonalityMean > 0) {
            $seasonality = array_map(fn($s) => $s / $seasonalityMean, $seasonality);
        }
        return [$level, $trend, $seasonality];
    }

    public function forecast(array $data, int $steps): array
    {
        $n = count($data);
        if ($n < $this->seasonalPeriod * 2) {
            return (new DoubleExponentialSmoothingStrategy())->forecast($data, $steps);
        }

        list($alpha, $beta, $gamma) = $this->optimizeParameters($data);
        list($level, $trend, $seasonality) = $this->initHoltWinters($data, $alpha, $beta, $gamma);

        for ($t = 0; $t < $n; $t++) {
            $oldLevel = $level;
            $level = $alpha * $data[$t] + (1 - $alpha) * ($level + $trend);
            $trend = $beta * ($level - $oldLevel) + (1 - $beta) * $trend;
            $seasonIndex = $t % $this->seasonalPeriod;
            $seasonality[$seasonIndex] = $gamma * ($data[$t] / $level) + (1 - $gamma) * $seasonality[$seasonIndex];
        }

        $forecast = [];
        for ($k = 1; $k <= $steps; $k++) {
            $seasonIndex = ($n + $k - 1) % $this->seasonalPeriod;
            $forecast[] = ($level + $k * $trend) * $seasonality[$seasonIndex];
        }
        return $forecast;
    }

    public function getName(): string
    {
        return 'HoltWinters';
    }

    public function getReliabilityMessage(): string
    {
        return '✅ Прогноз имеет высокую точность с учетом сезонности.';
    }

    public function getMinMonths(): int
    {
        return 24;
    }

    public function getMaxMonths(): ?int
    {
        return null;
    }
}