<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Contracts\AnomalyDetectorInterface;
use Illuminate\Support\Collection;
class AnomalyService
{
    private AnomalyDetectorInterface $detector;

    public function __construct()
    {
        $this->detector = new IqrAnomalyDetector();
    }

    /**
     * Обнаруживает аномалии в транзакциях
     */
    public function detectAnomalies(Collection $transactions): Collection
    {
        return $this->detector->detect($transactions);
    }

    /**
     * Возвращает транзакции без аномалий
     */
    public function getCleanTransactions(Collection $transactions): Collection
    {
        return $this->detector->filterOutliers($transactions);
    }

    /**
     * Возвращает статистику по аномалиям
     */
    public function getAnomaliesStats(Collection $transactions): array
    {
        $anomalies = $this->detectAnomalies($transactions);

        return [
            'count' => $anomalies->count(),
            'total_amount' => $anomalies->sum('amount'),
            'percentage' => $transactions->count() > 0
                ? round(($anomalies->count() / $transactions->count()) * 100, 1)
                : 0
        ];
    }

    /**
     * Название используемого детектора
     */
    public function getDetectorName(): string
    {
        return $this->detector->getName();
    }
}