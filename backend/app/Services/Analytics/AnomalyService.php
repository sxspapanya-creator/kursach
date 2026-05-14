<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Contracts\AnomalyDetectorInterface;
use App\Services\Analytics\IqrAnomalyDetector;
use App\Models\Currency;
use App\Models\CurrencyRate;
use Illuminate\Support\Collection;

class AnomalyService
{
    private AnomalyDetectorInterface $detector;

    public function __construct()
    {
        $this->detector = new IqrAnomalyDetector();
    }

    /**
     * Возвращает список транзакций, которые АЛГОРИТМ считает аномальными
     * (для отображения в таблице)
     */
    public function getAnomaliesList(Collection $transactions): array
    {
        $algorithmAnomalies = $this->detector->detect($transactions);

        if ($algorithmAnomalies->isEmpty()) {
            return [];
        }

        $baseCurrency = Currency::where('code', 'BYN')->first();
        if (!$baseCurrency) {
            return [];
        }

        $result = [];
        foreach ($algorithmAnomalies as $transaction) {
            $category = $transaction->categories->first();

            $exchangeRate = null;
            $amountInByn = (float) $transaction->amount;

            if ($transaction->currency_id != $baseCurrency->id) {
                $rate = CurrencyRate::where('from_currency_id', $transaction->currency_id)
                    ->where('to_currency_id', $baseCurrency->id)
                    ->where('date', '<=', $transaction->date)
                    ->orderBy('date', 'desc')
                    ->first();
                if ($rate) {
                    $exchangeRate = $rate->rate;
                    $amountInByn = (float) $transaction->amount * $rate->rate;
                }
            }

            $result[] = [
                'id' => $transaction->id,
                'description' => $transaction->description,
                'date' => $transaction->date->format('Y-m-d'),
                'amount' => (float) $transaction->amount,
                'original_amount' => (float) $transaction->amount,
                'currency_code' => $transaction->currency->code ?? 'BYN',
                'currency_symbol' => $transaction->currency->symbol ?? 'Br',
                'exchange_rate' => $exchangeRate,
                'amount_in_byn' => round($amountInByn, 2),
                'payment_method' => $transaction->payment_method,
                'category_name' => $category->name ?? null,
                'category_color' => $category->color ?? '#3498db',
                'is_anomaly' => $transaction->is_anomaly ?? false,
            ];
        }

        return $result;
    }

    /**
     * Возвращает транзакции для прогноза
     * Учитывает ТОЛЬКО is_anomaly из БД
     */
    public function getCleanTransactions(Collection $transactions): Collection
    {
        return $transactions->filter(function ($transaction) {
            // Если пользователь отметил "это аномалия" - исключаем из прогноза
            if ($transaction->is_anomaly === true) {
                return false;
            }
            // Во всех остальных случаях (false или null) - включаем в прогноз
            return true;
        });
    }

    /**
     * Обновляет статус аномалии для транзакции
     */
    public function updateAnomalyStatus(int $transactionId, int $userId, bool $isAnomaly): bool
    {
        $transaction = \App\Models\Transaction::where('user_id', $userId)
            ->where('id', $transactionId)
            ->first();

        if (!$transaction) {
            return false;
        }

        $transaction->is_anomaly = $isAnomaly;
        return $transaction->save();
    }

    /**
     * Название используемого детектора
     */
    public function getDetectorName(): string
    {
        return $this->detector->getName();
    }
}