<?php

namespace App\Console\Commands;

use App\Services\CurrencyService;
use Illuminate\Console\Command;

class UpdateExchangeRates extends Command
{
    protected $signature = 'currencies:update';
    protected $description = 'Обновление курсов валют из API НБРБ';

    public function handle(CurrencyService $currencyService)
    {
        $this->info('Обновление курсов...');
        $currencyService->updateRates();
        $this->info('Курсы обновлены!');
    }
}