<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\CurrencyRate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurrencyService
{
    const NBRB_API_URL = 'https://api.nbrb.by/exrates';

    // Только эти 5 валют
    private array $requiredCurrencies = [
        'BYN' => ['name' => 'Белорусский рубль', 'symbol' => 'Br'],
        'RUB' => ['name' => 'Российский рубль', 'symbol' => '₽'],
        'USD' => ['name' => 'Доллар США', 'symbol' => '$'],
        'EUR' => ['name' => 'Евро', 'symbol' => '€'],
        'CNY' => ['name' => 'Китайский юань', 'symbol' => '¥'],
    ];

    /**
     * Главный метод: импорт 5 валют и их истории за последние N месяцев
     */
    public function importCurrenciesWithHistory(int $months = 6): void
    {
        $this->info("=== НАЧАЛО ИМПОРТА ВАЛЮТ ===");

        // 1. Создаем 5 валют
        $this->createRequiredCurrencies();

        // 2. Получаем базовую валюту (BYN)
        $byn = Currency::where('code', 'BYN')->first();
        if (!$byn) {
            Log::error("Базовая валюта BYN не найдена!");
            return;
        }

        // 3. Заполняем историю для BYN (курс 1 на все даты)
        $this->loadHistoryForByn($byn, $months);

        // 4. Загружаем историю для каждой валюты (кроме BYN)
        $currencies = Currency::where('code', '!=', 'BYN')
            ->whereIn('code', array_keys($this->requiredCurrencies))
            ->get();

        $this->info("\n=== ЗАГРУЗКА ИСТОРИИ ЗА {$months} МЕСЯЦЕВ ===");

        foreach ($currencies as $currency) {
            $this->loadHistoryForCurrency($currency, $byn, $months);
        }

        $this->info("\n=== ИМПОРТ ЗАВЕРШЕН УСПЕШНО ===");
    }

    /**
     * Создание только нужных 5 валют
     */
    private function createRequiredCurrencies(): void
    {
        $this->info("\nСоздание/обновление валют:");

        foreach ($this->requiredCurrencies as $code => $data) {
            Currency::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $data['name'],
                    'symbol' => $data['symbol'],
                    'is_base' => ($code === 'BYN'),
                ]
            );

            $this->info("  ✓ {$code} - {$data['name']}");
        }
    }

    /**
     * Заполнение истории для BYN (курс 1 на все даты)
     */
    private function loadHistoryForByn(Currency $byn, int $months): void
    {
        $startDate = Carbon::now()->subMonths($months);
        $endDate = Carbon::now();

        $currentDate = clone $startDate;
        $loadedCount = 0;

        $this->info("\nЗагрузка истории для BYN (Белорусский рубль):");

        while ($currentDate <= $endDate) {
            $dateString = $currentDate->format('Y-m-d');

            CurrencyRate::updateOrCreate(
                [
                    'from_currency_id' => $byn->id,
                    'to_currency_id' => $byn->id,
                    'date' => $dateString,
                ],
                ['rate' => 1]
            );
            $loadedCount++;

            if ($loadedCount % 10 === 0) {
                $this->info("    Загружено {$loadedCount} записей...");
            }

            $currentDate->addDay();
        }

        $this->info("    ✓ Итого: загружено {$loadedCount} записей");
    }

    /**
     * Загрузка истории для одной валюты к BYN
     */
    private function loadHistoryForCurrency(Currency $currency, Currency $byn, int $months): void
    {
        $startDate = Carbon::now()->subMonths($months);
        $endDate = Carbon::now();

        $currentDate = clone $startDate;
        $loadedCount = 0;
        $skippedCount = 0;

        $this->info("\nЗагрузка истории для {$currency->code} ({$currency->name}):");

        while ($currentDate <= $endDate) {
            $dateString = $currentDate->format('Y-m-d');

            // Проверяем, есть ли уже курс на эту дату
            $exists = CurrencyRate::where('from_currency_id', $currency->id)
                ->where('to_currency_id', $byn->id)
                ->where('date', $dateString)
                ->exists();

            if ($exists) {
                $skippedCount++;
                $currentDate->addDay();
                continue;
            }

            // Получаем курс за конкретную дату
            $rate = $this->fetchRateForDate($currency, $currentDate);

            if ($rate !== null) {
                CurrencyRate::create([
                    'from_currency_id' => $currency->id,
                    'to_currency_id' => $byn->id,
                    'rate' => $rate,
                    'date' => $dateString,
                ]);
                $loadedCount++;

                if ($loadedCount % 10 === 0) {
                    $this->info("    Загружено {$loadedCount} курсов...");
                }
            }

            $currentDate->addDay();
            usleep(50000);
        }

        $this->info("    ✓ Итого: загружено {$loadedCount}, пропущено {$skippedCount}");
    }

    /**
     * Получение курса валюты к BYN за конкретную дату из API
     */
    private function fetchRateForDate(Currency $currency, Carbon $date): ?float
    {
        $formattedDate = $date->format('Y-m-d');

        try {
            $response = Http::get(self::NBRB_API_URL . "/rates/{$currency->code}", [
                'ondate' => $formattedDate,
                'parammode' => 2
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['Cur_OfficialRate'])) {
                    $rate = $data['Cur_OfficialRate'] / $data['Cur_Scale'];
                    return round($rate, 6);
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error("Ошибка курса {$currency->code} на {$formattedDate}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Обновление текущих курсов для 5 валют
     */
    public function updateRates(): void
    {
        $this->info("\n=== ОБНОВЛЕНИЕ КУРСОВ ===");

        $today = now()->toDateString();
        $this->info("Дата обновления: {$today}");

        // Получаем BYN
        $byn = Currency::where('code', 'BYN')->first();
        if (!$byn) {
            Log::error("Базовая валюта BYN не найдена!");
            $this->info("❌ Базовая валюта BYN не найдена");
            return;
        }

        $updatedCount = 0;

        // 1. ОБНОВЛЯЕМ BYN: курс 1 к BYN
        CurrencyRate::updateOrCreate(
            [
                'from_currency_id' => $byn->id,
                'to_currency_id' => $byn->id,
                'date' => $today,
            ],
            ['rate' => 1]
        );
        $updatedCount++;
        $this->info("  ✓ BYN: 1 BYN = 1 BYN (обновлено)");

        // 2. Получаем курсы из API
        $response = Http::get(self::NBRB_API_URL . '/rates', [
            'parammode' => 2,
            'periodicity' => 0
        ]);

        if ($response->failed()) {
            Log::error("Не удалось получить курсы валют из API");
            $this->info("❌ Ошибка получения курсов из API");
            $this->info("\n  Обновлено {$updatedCount} курсов (только BYN)");
            return;
        }

        $apiRates = $response->json();

        // 3. Обновляем остальные валюты
        foreach ($apiRates as $apiRate) {
            $currencyCode = $apiRate['Cur_Abbreviation'];

            // Проверяем, входит ли валюта в наш список
            if (!in_array($currencyCode, array_keys($this->requiredCurrencies))) {
                continue;
            }

            // Пропускаем BYN (уже обновили)
            if ($currencyCode === 'BYN') {
                continue;
            }

            $currency = Currency::where('code', $currencyCode)->first();

            if ($currency) {
                $newRate = $apiRate['Cur_OfficialRate'] / $apiRate['Cur_Scale'];
                $newRate = round($newRate, 6);

                CurrencyRate::updateOrCreate(
                    [
                        'from_currency_id' => $currency->id,
                        'to_currency_id' => $byn->id,
                        'date' => $today,
                    ],
                    ['rate' => $newRate]
                );
                $updatedCount++;
                $this->info("  ✓ {$currencyCode}: 1 {$currencyCode} = {$newRate} BYN (обновлено)");
            }
        }

        $this->info("\n  Обновлено {$updatedCount} курсов");
    }

    /**
     * Обновление курса для одной валюты
     */
    public function updateRateForCurrency(Currency $currency): void
    {
        // Проверяем, разрешена ли валюта
        if (!in_array($currency->code, array_keys($this->requiredCurrencies))) {
            $this->info("Валюта {$currency->code} не входит в список разрешенных");
            return;
        }

        $byn = Currency::where('code', 'BYN')->first();
        $today = now()->toDateString();

        $this->info("Дата обновления: {$today}");

        if ($currency->code === 'BYN') {
            // Обновляем BYN
            CurrencyRate::updateOrCreate(
                [
                    'from_currency_id' => $currency->id,
                    'to_currency_id' => $currency->id,
                    'date' => $today,
                ],
                ['rate' => 1]
            );
            $this->info("Курс BYN обновлен: 1");
            return;
        }

        try {
            $response = Http::get(self::NBRB_API_URL . '/rates', [
                'parammode' => 2,
                'periodicity' => 0
            ]);

            if ($response->failed()) {
                $this->info("❌ Ошибка получения курса из API");
                return;
            }

            $rates = $response->json();
            $apiRate = collect($rates)->firstWhere('Cur_Abbreviation', $currency->code);

            if ($apiRate) {
                $newRate = $apiRate['Cur_OfficialRate'] / $apiRate['Cur_Scale'];
                $newRate = round($newRate, 6);

                CurrencyRate::updateOrCreate(
                    [
                        'from_currency_id' => $currency->id,
                        'to_currency_id' => $byn->id,
                        'date' => $today,
                    ],
                    ['rate' => $newRate]
                );
                $this->info("Курс {$currency->code} обновлен: {$newRate}");
            }
        } catch (\Exception $e) {
            Log::error("Ошибка: " . $e->getMessage());
        }
    }

    /**
     * Получить текущий курс валюты (последний доступный)
     */
    public function getCurrentRate(Currency $currency): ?float
    {
        if ($currency->code === 'BYN') {
            return 1;
        }

        $byn = Currency::where('code', 'BYN')->first();

        $rate = CurrencyRate::where('from_currency_id', $currency->id)
            ->where('to_currency_id', $byn->id)
            ->orderBy('date', 'desc')
            ->first();

        return $rate ? $rate->rate : null;
    }

    /**
     * Получить курс валюты на конкретную дату
     */
    public function getRateOnDate(Currency $currency, string $date): ?float
    {
        if ($currency->code === 'BYN') {
            return 1;
        }

        $byn = Currency::where('code', 'BYN')->first();

        $rate = CurrencyRate::where('from_currency_id', $currency->id)
            ->where('to_currency_id', $byn->id)
            ->where('date', $date)
            ->first();

        return $rate ? $rate->rate : null;
    }

    /**
     * Получить все 5 валют с их текущими курсами
     */
    public function getAllCurrenciesWithRates(): array
    {
        $currencies = Currency::whereIn('code', array_keys($this->requiredCurrencies))->get();
        $byn = Currency::where('code', 'BYN')->first();

        return $currencies->map(function ($currency) use ($byn) {
            // Получаем последний курс для валюты
            $latestRate = CurrencyRate::where('from_currency_id', $currency->id)
                ->where('to_currency_id', $currency->code === 'BYN' ? $currency->id : $byn->id)
                ->orderBy('date', 'desc')
                ->first();

            return [
                'id' => $currency->id,
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'is_base' => $currency->is_base,
                'rate' => $currency->code === 'BYN' ? 1 : ($latestRate ? $latestRate->rate : null),
                'rate_date' => $latestRate ? $latestRate->date : null,
            ];
        })->toArray();
    }

    /**
     * Получить историю курса валюты за период
     */
    public function getCurrencyHistory(string $currencyCode, int $days = 180): array
    {
        if (!in_array($currencyCode, array_keys($this->requiredCurrencies))) {
            return [];
        }

        $currency = Currency::where('code', $currencyCode)->first();
        if (!$currency) {
            return [];
        }

        $byn = Currency::where('code', 'BYN')->first();

        $rates = CurrencyRate::where('from_currency_id', $currency->id)
            ->where('to_currency_id', $currency->code === 'BYN' ? $currency->id : $byn->id)
            ->where('date', '>=', Carbon::now()->subDays($days))
            ->orderBy('date', 'asc')
            ->get();

        return $rates->map(function ($rate) {
            return [
                'date' => $rate->date->format('Y-m-d'),
                'rate' => $rate->rate
            ];
        })->toArray();
    }

    /**
     * Конвертировать сумму из одной валюты в другую
     */
    public function convert(float $amount, Currency $fromCurrency, Currency $toCurrency, ?string $date = null): ?float
    {
        if ($fromCurrency->id === $toCurrency->id) {
            return $amount;
        }

        $date = $date ?? now()->toDateString();
        $byn = Currency::where('code', 'BYN')->first();

        // Получаем курс from → BYN
        $fromRate = $fromCurrency->code === 'BYN'
            ? 1
            : CurrencyRate::where('from_currency_id', $fromCurrency->id)
                ->where('to_currency_id', $byn->id)
                ->where('date', $date)
                ->first()?->rate;

        // Получаем курс to → BYN
        $toRate = $toCurrency->code === 'BYN'
            ? 1
            : CurrencyRate::where('from_currency_id', $toCurrency->id)
                ->where('to_currency_id', $byn->id)
                ->where('date', $date)
                ->first()?->rate;

        if (!$fromRate || !$toRate) {
            return null;
        }

        // Конвертация через BYN
        $amountInByn = $amount * $fromRate;
        return $amountInByn / $toRate;
    }

    /**
     * Вспомогательный метод для вывода в консоль
     */
    private function info(string $message): void
    {
        if (app()->runningInConsole()) {
            echo $message . PHP_EOL;
        }
    }
}