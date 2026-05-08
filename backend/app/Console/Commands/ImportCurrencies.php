<?php

namespace App\Console\Commands;

use App\Services\CurrencyService;
use Illuminate\Console\Command;

class ImportCurrencies extends Command
{
    protected $signature = 'currencies:import {--history : Загрузить историю за 6 месяцев} {--months=6 : Количество месяцев истории}';
    protected $description = 'Импорт 5 валют (BYN, RUB, USD, EUR, CNY) с историей к BYN';

    public function handle(CurrencyService $currencyService)
    {
        if ($this->option('history')) {
            $months = (int) $this->option('months');
            $this->info("Импорт 5 валют с историей за {$months} месяцев...");
            $currencyService->importCurrenciesWithHistory($months);
        } else {
            $this->info("Импорт 5 валют без истории...");
            $currencyService->importCurrenciesWithHistory(0); // Только создаст валюты
        }

        $this->info("\n✅ Готово!");
    }
}