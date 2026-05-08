<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\CurrencyRate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurrencyService
{
    const NBRB_API_URL = 'https://api.nbrb.by/exrates';

    // Получить список всех валют из API и сохранить в БД
    public function importCurrencies(): void
    {
        $response = Http::get(self::NBRB_API_URL . '/currencies');

        if ($response->failed()) {
            Log::error("Не удалось получить список валют");
            return;
        }

        $currencies = $response->json();

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['Cur_Abbreviation']],
                [
                    'name' => $currency['Cur_Name'],
                    'symbol' => $currency['Cur_Abbreviation'],
                    'is_base' => $currency['Cur_Abbreviation'] === 'BYN',
                ]
            );
        }
    }

    // Обновить курсы для всех валют в БД (ОДНИМ ЗАПРОСОМ)
    public function updateRates(): void
    {
        $response = Http::get(self::NBRB_API_URL . '/rates', [
            'parammode' => 2,
            'periodicity' => 0
        ]);

        if ($response->failed()) {
            Log::error("Не удалось получить курсы валют из API");
            return;
        }

        $apiRates = $response->json();

        // Получаем BYN один раз
        $byn = Currency::where('code', 'BYN')->first();
        if ($byn) {
            $byn->rate = 1;
            $byn->last_synced_at = now();
            $byn->save();
        }

        // Обновляем курсы для валют, которые есть в ответе API
        foreach ($apiRates as $apiRate) {
            $currency = Currency::where('code', $apiRate['Cur_Abbreviation'])->first();

            if ($currency) {
                $newRate = $apiRate['Cur_OfficialRate'] / $apiRate['Cur_Scale'];
                $currency->rate = $newRate;
                $currency->last_synced_at = now();
                $currency->save();

                // Сохраняем в историю
                CurrencyRate::create([
                    'from_currency_id' => $currency->id,
                    'to_currency_id' => $byn->id,
                    'rate' => $newRate,
                    'date' => now()->toDateString(),
                ]);
            }
        }
    }

    // Обновить курс одной валюты (для ручного использования)
    public function updateRateForCurrency(Currency $currency): void
    {
        if ($currency->code === 'BYN') {
            $currency->rate = 1;
            $currency->last_synced_at = now();
            $currency->save();
            return;
        }

        try {
            $response = Http::get(self::NBRB_API_URL . '/rates', [
                'parammode' => 2,
                'periodicity' => 0
            ]);

            if ($response->failed()) {
                return;
            }

            $rates = $response->json();
            $apiRate = collect($rates)->firstWhere('Cur_Abbreviation', $currency->code);

            if ($apiRate) {
                $newRate = $apiRate['Cur_OfficialRate'] / $apiRate['Cur_Scale'];
                $currency->rate = $newRate;
                $currency->last_synced_at = now();
                $currency->save();

                $byn = Currency::where('code', 'BYN')->first();
                CurrencyRate::create([
                    'from_currency_id' => $currency->id,
                    'to_currency_id' => $byn->id,
                    'rate' => $newRate,
                    'date' => now()->toDateString(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Ошибка: " . $e->getMessage());
        }
    }
}