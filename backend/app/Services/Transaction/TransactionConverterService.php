<?php

namespace App\Services\Transaction;

use App\Models\Transaction;
use App\Models\Currency;
use App\Models\CurrencyRate;
use Illuminate\Support\Collection;

class TransactionConverterService
{
    private ?Currency $baseCurrency;

    public function __construct()
    {
        $this->baseCurrency = Currency::where('code', 'BYN')->first();
    }

    /**
     * Добавляет курсы конвертации к коллекции транзакций
     */
    public function addExchangeRates(Collection $transactions): Collection
    {
        if (!$this->baseCurrency) {
            return $transactions;
        }

        return $transactions->map(function ($transaction) {
            if ($transaction->currency_id && $transaction->currency_id != $this->baseCurrency->id) {
                $rate = $this->getExchangeRate($transaction->currency_id, $transaction->date);
                $transaction->exchange_rate = $rate ? $rate->rate : null;
                $transaction->amount_in_byn = $rate ? $transaction->amount * $rate->rate : $transaction->amount;
            } else {
                $transaction->exchange_rate = 1;
                $transaction->amount_in_byn = $transaction->amount;
            }

            return $transaction;
        });
    }

    /**
     * Получить курс валюты на дату
     */
    private function getExchangeRate(int $currencyId, string $date): ?object
    {
        // Сначала ищем точное совпадение
        $rate = CurrencyRate::where('from_currency_id', $currencyId)
            ->where('to_currency_id', $this->baseCurrency->id)
            ->whereDate('date', $date)
            ->first();

        // Если нет - ищем ближайший предыдущий
        if (!$rate) {
            $rate = CurrencyRate::where('from_currency_id', $currencyId)
                ->where('to_currency_id', $this->baseCurrency->id)
                ->whereDate('date', '<=', $date)
                ->orderBy('date', 'desc')
                ->first();
        }

        return $rate;
    }

    /**
     * Проверить наличие курса для валюты на дату
     */
    public function validateExchangeRate(int $currencyId, string $date): bool
    {
        if (!$this->baseCurrency) {
            return false;
        }

        $currency = Currency::find($currencyId);
        if (!$currency || $currency->code === 'BYN') {
            return true;
        }

        $rate = $this->getExchangeRate($currencyId, $date);
        return $rate !== null;
    }

    public function getBaseCurrency(): ?Currency
    {
        return $this->baseCurrency;
    }
}