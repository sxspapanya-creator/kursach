<?php

namespace App\Services\Analytics;

class MetricsCalculatorService
{
    /**
     * Расчет метрик качества модели (MAPE, RMSE)
     */
    public function calculateModelMetrics(array $historical, array $forecast): array
    {
        if (empty($historical) || empty($forecast)) {
            return ['mape' => 0, 'mse' => 0, 'rmse' => 0, 'interpretation' => 'Нет данных'];
        }

        $n = min(count($historical), count($forecast));
        $mapeSum = 0;
        $mseSum = 0;
        $validCount = 0;

        for ($i = 0; $i < $n; $i++) {
            $actual = $historical[count($historical) - $n + $i];
            $predicted = $forecast[$i];
            if ($actual > 0) {
                $mapeSum += abs(($actual - $predicted) / $actual);
                $validCount++;
            }
            $mseSum += pow($actual - $predicted, 2);
        }

        $mape = $validCount > 0 ? ($mapeSum / $validCount) * 100 : 0;
        $mse = $n > 0 ? $mseSum / $n : 0;
        $rmse = sqrt($mse);

        $interpretation = match(true) {
            $mape < 10 => 'Высокая точность',
            $mape < 20 => 'Хорошая точность',
            $mape < 30 => 'Приемлемая точность',
            default => 'Низкая точность'
        };

        return [
            'mape' => round($mape, 1),
            'mse' => round($mse, 2),
            'rmse' => round($rmse, 2),
            'interpretation' => $interpretation
        ];
    }

    /**
     * Расчет коэффициента вариации (CV) для массива расходов
     */
    public function calculateCoefficientOfVariation(array $monthlyExpenses): array
    {
        $values = array_filter($monthlyExpenses, fn($v) => $v > 0);
        $n = count($values);

        if ($n < 3) {
            return [
                'cv' => null,
                'cv_percent' => null,
                'level' => 'low',
                'text' => 'Недостаточно данных'
            ];
        }

        $mean = array_sum($values) / $n;
        $variance = array_sum(array_map(fn($x) => pow($x - $mean, 2), $values)) / $n;
        $cv = sqrt($variance) / $mean;
        $cvPercent = round($cv * 100, 1);

        if ($cv < 0.15) {
            $level = 'high';
            $text = 'Стабильные расходы';
        } elseif ($cv < 0.30) {
            $level = 'medium';
            $text = 'Умеренные колебания';
        } elseif ($cv < 0.50) {
            $level = 'low';
            $text = 'Нестабильные расходы';
        } else {
            $level = 'low';
            $text = 'Очень нестабильные расходы';
        }

        return [
            'cv' => round($cv, 3),
            'cv_percent' => $cvPercent,
            'level' => $level,
            'text' => $text,
        ];
    }

    /**
     * Расчет тренда из данных
     */
    public function calculateTrend(array $data): float
    {
        if (count($data) < 4) return 1.0;
        $n = count($data);
        $firstHalf = array_sum(array_slice($data, 0, floor($n / 2))) / floor($n / 2);
        $secondHalf = array_sum(array_slice($data, floor($n / 2))) / ceil($n / 2);
        if ($firstHalf == 0) return 1.0;

        return $secondHalf / $firstHalf;  // ← убрать max/min
    }

    /**
     * Расчет сезонного фактора
     */
    public function calculateSeasonalFactor(array $data, float $currentMonthAmount, float $lastYearAvg): float
    {
        if ($lastYearAvg == 0) return 1.0;
        return $currentMonthAmount / $lastYearAvg;
    }
}