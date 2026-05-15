<?php

namespace App\Services\Transaction;

use App\Models\Transaction;
use App\Models\Currency;
use App\Models\CurrencyRate;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TransactionQueryService
{
    /**
     * Построение запроса с фильтрацией
     */
    public function buildQuery(int $userId, array $filters, bool $includeAnomalies = false): \Illuminate\Database\Eloquent\Builder
    {
        $query = Transaction::where('user_id', $userId)
            ->with(['categories', 'currency']);

        // Фильтрация по аномалиям
        if (!$includeAnomalies) {
            $query->where('is_anomaly', false);
        }

        // Фильтрация по типу
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Фильтрация по категориям
        if (!empty($filters['category_ids'])) {
            $categoryIds = array_values(array_unique($filters['category_ids']));
            $query->whereHas('categories', fn($q) => $q->whereIn('categories.id', $categoryIds));
        }

        // Фильтрация по месяцу
        if (isset($filters['month'])) {
            $query->whereMonth('date', $filters['month']);
        }

        // Фильтрация по году
        if (isset($filters['year'])) {
            $query->whereYear('date', $filters['year']);
        }

        // Фильтрация по диапазону дат
        if (isset($filters['date_from'])) {
            $query->where('date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('date', '<=', $filters['date_to']);
        }

        return $query->orderBy('date', 'desc')->orderBy('created_at', 'desc');
    }

    /**
     * Получить транзакции с пагинацией
     */
    public function getTransactions(int $userId, array $filters, bool $includeAnomalies = false, ?int $limit = 50, bool $fetchAll = false): Collection
    {
        $query = $this->buildQuery($userId, $filters, $includeAnomalies);

        if ($fetchAll) {
            return $query->get();
        }

        return $query->take($limit ?? 50)->get();
    }

    /**
     * Получить последние транзакции
     */
    public function getRecentTransactions(int $userId, int $limit = 10, bool $includeAnomalies = false): Collection
    {
        $query = Transaction::where('user_id', $userId)
            ->with(['categories', 'currency'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc');

        if (!$includeAnomalies) {
            $query->where('is_anomaly', false);
        }

        return $query->take($limit)->get();
    }

    /**
     * Получить сводку по месяцу
     */
    public function getMonthlySummary(int $userId, int $month, int $year, bool $excludeAnomalies = true): array
    {
        $incomeQuery = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->whereMonth('date', $month)
            ->whereYear('date', $year);

        $expenseQuery = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereMonth('date', $month)
            ->whereYear('date', $year);

        if ($excludeAnomalies) {
            $incomeQuery->where('is_anomaly', false);
            $expenseQuery->where('is_anomaly', false);
        }

        $income = $incomeQuery->sum('amount');
        $expenses = $expenseQuery->sum('amount');

        return [
            'income' => (float) $income,
            'expenses' => (float) $expenses,
            'balance' => (float) ($income - $expenses),
            'period' => ['month' => $month, 'year' => $year],
            'excluded_anomalies' => $excludeAnomalies
        ];
    }
}