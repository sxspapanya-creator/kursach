<?php

namespace App\Services\Analytics;

use App\Models\Transaction;
use App\Models\Currency;
use App\Models\CurrencyRate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CurrencyConverterService
{
    private Currency $baseCurrency;

    public function __construct()
    {
        $this->baseCurrency = Currency::where('code', 'BYN')->first();
        if (!$this->baseCurrency) {
            throw new \RuntimeException('Базовая валюта BYN не найдена');
        }
    }

    /**
     * Загружает курсы валют для списка транзакций
     */
    public function loadRatesForTransactions(Collection $transactions): array
    {
        if ($transactions->isEmpty()) return [];

        $currencyIds = $transactions->pluck('currency_id')->unique()->filter(fn($id) => $id != $this->baseCurrency->id);
        if ($currencyIds->isEmpty()) return [];

        $minDate = $transactions->min('date');
        $maxDate = $transactions->max('date');

        $allRates = CurrencyRate::whereIn('from_currency_id', $currencyIds)
            ->where('to_currency_id', $this->baseCurrency->id)
            ->whereBetween('date', [$minDate, $maxDate])
            ->orderBy('date', 'asc')
            ->get();

        $rates = [];
        foreach ($allRates as $rate) {
            $rates[$rate->from_currency_id][$rate->date->toDateString()] = $rate->rate;
        }
        return $rates;
    }

    /**
     * Получает курс валюты на дату (сначала из кэша, потом из БД)
     */
    private function getExchangeRate(int $currencyId, \Carbon\Carbon $date, array $ratesCache = []): ?float
    {
        if ($currencyId == $this->baseCurrency->id) return 1;

        // Поиск в кэше
        if (isset($ratesCache[$currencyId])) {
            $dateKey = $date->toDateString();
            if (isset($ratesCache[$currencyId][$dateKey])) {
                return $ratesCache[$currencyId][$dateKey];
            }

            // Ближайшая доступная дата
            $availableDates = array_keys($ratesCache[$currencyId]);
            $lastDate = null;
            foreach ($availableDates as $d) {
                if ($d <= $dateKey) $lastDate = $d;
                else break;
            }
            if ($lastDate) return $ratesCache[$currencyId][$lastDate];
        }

        // Поиск в БД
        $rate = CurrencyRate::where('from_currency_id', $currencyId)
            ->where('to_currency_id', $this->baseCurrency->id)
            ->where('date', '<=', $date)
            ->orderBy('date', 'desc')
            ->first();

        return $rate ? $rate->rate : null;
    }

    /**
     * Конвертирует транзакцию в базовую валюту
     */
    public function convert(Transaction $transaction, array $ratesCache = []): float
    {
        $rate = $this->getExchangeRate($transaction->currency_id, $transaction->date, $ratesCache);

        if ($rate === null) {
            Log::warning("Курс не найден для валюты {$transaction->currency_id} на дату {$transaction->date}");
            return (float) $transaction->amount;
        }

        return (float) $transaction->amount * $rate;
    }

    /**
     * Конвертирует сумму в базовую валюту по курсу на дату
     */
    public function convertAmount(float $amount, int $currencyId, \Carbon\Carbon $date): float
    {
        if ($currencyId == $this->baseCurrency->id) return $amount;

        $rate = CurrencyRate::where('from_currency_id', $currencyId)
            ->where('to_currency_id', $this->baseCurrency->id)
            ->where('date', '<=', $date)
            ->orderBy('date', 'desc')
            ->first();

        if (!$rate) {
            Log::warning("Курс не найден для валюты {$currencyId} на дату {$date}");
            return $amount;
        }

        return $amount * $rate->rate;
    }

    public function getBaseCurrency(): Currency
    {
        return $this->baseCurrency;
    }
}