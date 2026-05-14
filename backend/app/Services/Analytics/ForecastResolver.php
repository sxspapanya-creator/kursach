<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Contracts\ForecastMethodInterface;

class ForecastResolver
{
    /**
     * @var array<ForecastMethodInterface>
     */
    private array $strategies = [];

    public function __construct()
    {
        $this->strategies = [
            new SimpleExtrapolationStrategy(),
            new LinearRegressionStrategy(),
            new DoubleExponentialSmoothingStrategy(),
            new HoltWintersStrategy(),
        ];
    }

    public function resolve(int $monthsCount): ?ForecastMethodInterface
    {
        if ($monthsCount < 3) {
            return null;
        }

        foreach ($this->strategies as $strategy) {
            if ($monthsCount >= $strategy->getMinMonths()) {
                if ($strategy->getMaxMonths() === null || $monthsCount <= $strategy->getMaxMonths()) {
                    return $strategy;
                }
            }
        }

        return null;
    }

    /**
     * @return array<ForecastMethodInterface>
     */
    public function getAllStrategies(): array
    {
        return $this->strategies;
    }
}