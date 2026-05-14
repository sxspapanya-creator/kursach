<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Contracts\ForecastMethodInterface;
class ForecastService
{
    private ForecastResolver $resolver;

    public function __construct(ForecastResolver $resolver)
    {
        $this->resolver = $resolver;
    }

    /**
     * Выполняет прогноз на основе чистых данных (без аномалий)
     */
    public function forecast(array $historicalData, int $steps = 3): ?array
    {
        $monthsCount = count($historicalData);

        $strategy = $this->resolver->resolve($monthsCount);

        if (!$strategy) {
            return null;
        }

        return $strategy->forecast($historicalData, $steps);
    }

    /**
     * Возвращает стратегию для заданного количества месяцев
     */
    public function getStrategy(int $monthsCount): ?ForecastMethodInterface
    {
        return $this->resolver->resolve($monthsCount);
    }

    /**
     * Возвращает информацию о стратегии
     */
    public function getStrategyInfo(int $monthsCount): ?array
    {
        $strategy = $this->resolver->resolve($monthsCount);

        if (!$strategy) {
            return null;
        }

        return [
            'name' => $strategy->getName(),
            'message' => $strategy->getReliabilityMessage(),
            'min_months' => $strategy->getMinMonths(),
            'max_months' => $strategy->getMaxMonths()
        ];
    }
}