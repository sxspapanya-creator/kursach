<?php

namespace App\Services\Analytics;

use App\Services\Analytics\Contracts\AnomalyDetectorInterface;
use App\Models\Transaction;
use App\Models\Currency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class IqrAnomalyDetector implements AnomalyDetectorInterface
{
    private Currency $baseCurrency;
    private array $ratesCache = [];
    private bool $ratesLoaded = false;

    public function __construct()
    {
        $this->baseCurrency = Currency::where('code', 'BYN')->first();
    }

    /**
     * Загружает курсы валют для транзакций
     */
    private function loadRates(Collection $transactions): void
    {
        if ($this->ratesLoaded || $transactions->isEmpty()) return;

        $currencyIds = $transactions->pluck('currency_id')->unique()->filter(fn($id) => $id != $this->baseCurrency->id);
        if ($currencyIds->isEmpty()) {
            $this->ratesLoaded = true;
            return;
        }

        $minDate = $transactions->min('date');
        $maxDate = $transactions->max('date');

        $allRates = \App\Models\CurrencyRate::whereIn('from_currency_id', $currencyIds)
            ->where('to_currency_id', $this->baseCurrency->id)
            ->whereBetween('date', [$minDate, $maxDate])
            ->orderBy('date', 'asc')
            ->get();

        foreach ($allRates as $rate) {
            $currencyId = $rate->from_currency_id;
            $dateKey = $rate->date->toDateString();
            $this->ratesCache[$currencyId][$dateKey] = $rate->rate;
        }
        $this->ratesLoaded = true;
    }

    /**
     * Получает курс из кеша
     */
    private function getRate(int $currencyId, string $dateKey): ?float
    {
        if ($currencyId == $this->baseCurrency->id) return 1;
        if (!isset($this->ratesCache[$currencyId])) return null;

        if (isset($this->ratesCache[$currencyId][$dateKey])) {
            return $this->ratesCache[$currencyId][$dateKey];
        }

        $availableDates = array_keys($this->ratesCache[$currencyId]);
        $lastDate = null;
        foreach ($availableDates as $d) {
            if ($d <= $dateKey) $lastDate = $d;
            else break;
        }
        return $lastDate ? $this->ratesCache[$currencyId][$lastDate] : null;
    }

    /**
     * Конвертирует сумму в базовую валюту
     */
    private function convertToBaseCurrency(Transaction $transaction): float
    {
        if ($transaction->currency_id == $this->baseCurrency->id) {
            return (float)$transaction->amount;
        }

        $dateKey = $transaction->date->toDateString();
        $rate = $this->getRate($transaction->currency_id, $dateKey);

        if ($rate === null) {
            Log::warning("Курс не найден для валюты {$transaction->currency_id} на дату {$transaction->date}");
            return (float)$transaction->amount;
        }

        return (float)$transaction->amount * $rate;
    }

    /**
     * Рассчитывает квартили по массиву значений
     */
    private function calculateQuartiles(array $values): array
    {
        $n = count($values);
        sort($values);

        $q1Index = (int)($n * 0.25);
        $q3Index = (int)($n * 0.75);

        return [
            'q1' => $values[$q1Index],
            'q3' => $values[$q3Index],
            'iqr' => $values[$q3Index] - $values[$q1Index]
        ];
    }

    /**
     * Детектит аномалии по алгоритму (ДЛЯ ОТОБРАЖЕНИЯ В ТАБЛИЦЕ)
     * НЕ учитывает ручные метки пользователя
     */
    public function detect(Collection $transactions): Collection
    {
        if ($transactions->isEmpty()) return collect();

        $this->loadRates($transactions);

        $groupedByCategory = $transactions->groupBy(function ($transaction) {
            $category = $transaction->categories->first();
            return $category ? $category->id : 'uncategorized';
        });

        $algorithmAnomalies = collect();

        foreach ($groupedByCategory as $categoryId => $categoryTransactions) {
            if ($categoryTransactions->count() < 8) {
                continue;
            }

            $amounts = [];
            $transactionMap = [];

            foreach ($categoryTransactions as $transaction) {
                $amountInBase = $this->convertToBaseCurrency($transaction);
                $amounts[] = $amountInBase;
                $transactionMap[$transaction->id] = $transaction;
            }

            $quartiles = $this->calculateQuartiles($amounts);
            $threshold = $quartiles['q3'] + (3 * $quartiles['iqr']);

            foreach ($transactionMap as $id => $transaction) {
                $amount = $this->convertToBaseCurrency($transaction);
                if ($amount > $threshold) {
                    $algorithmAnomalies->push($transaction);
                }
            }
        }

        return $algorithmAnomalies->unique('id');
    }

    /**
     * Возвращает транзакции для прогноза
     * Учитывает ТОЛЬКО ручные метки is_anomaly из БД
     * АЛГОРИТМ НЕ ИСПОЛЬЗУЕТСЯ!
     */
    public function filterOutliers(Collection $transactions): Collection
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

    public function getName(): string
    {
        return 'IQR по категориям (только для отображения)';
    }
}