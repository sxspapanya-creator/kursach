<?php

namespace App\Services\Category;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\CurrencyRate;  // ← ИСПРАВЛЕНО: добавляем импорт
use App\Services\Analytics\CurrencyConverterService;
use Illuminate\Support\Collection;

class CategoryStatsService
{
    private CurrencyConverterService $currencyConverter;
    private array $supportedCurrencies = ['BYN', 'RUB', 'USD', 'EUR', 'CNY'];
    private array $currencySymbols = [
        'BYN' => 'Br',
        'RUB' => '₽',
        'USD' => '$',
        'EUR' => '€',
        'CNY' => '¥'
    ];

    public function __construct(CurrencyConverterService $currencyConverter)
    {
        $this->currencyConverter = $currencyConverter;
    }

    /**
     * Получить категории с транзакциями и разбивкой по валютам
     */
    public function getCategoriesWithTransactions(int $userId, int $month, int $year): array
    {
        $categories = Category::where('user_id', $userId)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $result = [];

        foreach ($categories as $category) {
            $transactions = $this->getCategoryTransactions($userId, $category->id, $month, $year);
            $stats = $this->calculateStats($transactions);
            $lastTransaction = $this->getLastCategoryTransaction($userId, $category->id);
            $allTimeCount = $this->getCategoryTransactionCount($userId, $category->id);

            $result[] = [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'color' => $category->color,
                'budget_limit' => $category->budget_limit,
                'transaction_count' => $transactions->count(),
                'total_amount_byn' => round($stats['total_byn'], 2),
                'currency_stats' => $stats['currency_stats'],
                'last_transaction_date' => $lastTransaction?->date,
                'all_time_count' => $allTimeCount,
                'updated_at' => $category->updated_at->format('Y-m-d H:i:s')
            ];
        }

        return $result;
    }

    /**
     * Получить категории со статистикой (месяц + все время)
     */
    public function getCategoriesWithFullStats(int $userId, int $month, int $year): array
    {
        $categories = Category::where('user_id', $userId)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $result = [];

        foreach ($categories as $category) {
            $monthTransactions = $this->getCategoryTransactions($userId, $category->id, $month, $year);
            $allTransactions = $this->getAllCategoryTransactions($userId, $category->id);

            $monthStats = $this->calculateStats($monthTransactions);
            $allTimeStats = $this->calculateStats($allTransactions);

            $lastTransaction = $this->getLastCategoryTransaction($userId, $category->id);

            $result[] = [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'color' => $category->color,
                'budget_limit' => $category->budget_limit,
                'transaction_count' => $monthTransactions->count(),
                'total_amount' => round($monthStats['total_byn'], 2),
                'currency_stats' => $monthStats['currency_stats'],
                'all_time_count' => $allTransactions->count(),
                'all_time_total_byn' => round($allTimeStats['total_byn'], 2),
                'last_transaction_date' => $lastTransaction?->date,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
            ];
        }

        return $result;
    }

    /**
     * Получить транзакции категории за месяц
     */
    private function getCategoryTransactions(int $userId, int $categoryId, int $month, int $year): Collection
    {
        return Transaction::where('user_id', $userId)
            ->whereHas('categories', fn($q) => $q->where('category_id', $categoryId))
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->with('currency')
            ->get();
    }

    /**
     * Получить все транзакции категории
     */
    private function getAllCategoryTransactions(int $userId, int $categoryId): Collection
    {
        return Transaction::where('user_id', $userId)
            ->whereHas('categories', fn($q) => $q->where('category_id', $categoryId))
            ->with('currency')
            ->get();
    }

    /**
     * Получить последнюю транзакцию категории
     */
    private function getLastCategoryTransaction(int $userId, int $categoryId): ?Transaction
    {
        return Transaction::where('user_id', $userId)
            ->whereHas('categories', fn($q) => $q->where('category_id', $categoryId))
            ->orderBy('date', 'desc')
            ->first();
    }

    /**
     * Получить количество транзакций категории за все время
     */
    private function getCategoryTransactionCount(int $userId, int $categoryId): int
    {
        return Transaction::where('user_id', $userId)
            ->whereHas('categories', fn($q) => $q->where('category_id', $categoryId))
            ->count();
    }

    /**
     * Рассчитать статистику по транзакциям
     */
    private function calculateStats(Collection $transactions): array
    {
        $totalByn = 0;
        $currencyStats = $this->initCurrencyStats();

        foreach ($transactions as $transaction) {
            $amount = $transaction->amount;
            $currencyCode = $transaction->currency?->code ?? 'BYN';
            $currencyId = $transaction->currency_id;
            $date = $transaction->date;

            // Конвертация в BYN
            $rate = $this->getExchangeRate($currencyId, $currencyCode, $date);
            $totalByn += $amount * $rate;

            // Статистика по валютам
            if (isset($currencyStats[$currencyCode])) {
                $currencyStats[$currencyCode]['total_amount'] += $amount;
                $currencyStats[$currencyCode]['transaction_count']++;
            }
        }

        // Фильтруем только валюты с транзакциями
        $filteredStats = array_values(array_filter($currencyStats, fn($stat) => $stat['transaction_count'] > 0));

        return [
            'total_byn' => $totalByn,
            'currency_stats' => $filteredStats
        ];
    }

    /**
     * Инициализация структуры для статистики по валютам
     */
    private function initCurrencyStats(): array
    {
        $stats = [];
        foreach ($this->supportedCurrencies as $code) {
            $stats[$code] = [
                'currency_code' => $code,
                'currency_symbol' => $this->currencySymbols[$code],
                'total_amount' => 0,
                'transaction_count' => 0
            ];
        }
        return $stats;
    }

    /**
     * Получить курс валюты (используем CurrencyRate, а не Currency!)
     */
    private function getExchangeRate(int $currencyId, string $currencyCode, $date): float
    {
        if ($currencyCode === 'BYN') return 1;

        $baseCurrency = $this->currencyConverter->getBaseCurrency();

        // Ищем курс на точную дату
        $rate = CurrencyRate::where('from_currency_id', $currencyId)
            ->where('to_currency_id', $baseCurrency->id)
            ->whereDate('date', $date)
            ->first();

        // Если не нашли - ищем ближайший предыдущий
        if (!$rate) {
            $rate = CurrencyRate::where('from_currency_id', $currencyId)
                ->where('to_currency_id', $baseCurrency->id)
                ->whereDate('date', '<=', $date)
                ->orderBy('date', 'desc')
                ->first();
        }

        return $rate ? $rate->rate : 1;
    }
}