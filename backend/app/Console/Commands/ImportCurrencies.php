<?php

namespace App\Console\Commands;

use App\Services\CurrencyService;
use Illuminate\Console\Command;

class ImportCurrencies extends Command
{
    protected $signature = 'currencies:import {--months=6}';
    protected $description = 'Импорт списка валют из API НБРБ';

    public function handle(CurrencyService $currencyService)
    {
        $months = (int) $this->option('months');
        $this->info('Импорт валют...');
        $currencyService->importCurrencies();
        $this->info('Готово!');
    }
}