<?php

namespace App\Console\Commands;

use App\Services\CurrencyService;
use Illuminate\Console\Command;

class UpdateExchangeRates extends Command
{
    protected $signature = 'currencies:update';
    protected $description = 'Обновление курсов для 5 валют (BYN, RUB, USD, EUR, CNY)';

    public function handle(CurrencyService $currencyService)
    {
        $this->info("=== ОБНОВЛЕНИЕ КУРСОВ ВАЛЮТ ===");
        $this->info("Валюты: BYN, RUB, USD, EUR, CNY\n");

        $currencyService->updateRates();

        $this->info("\n✅ Курсы обновлены!");
    }
}