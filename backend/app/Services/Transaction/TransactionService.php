<?php

namespace App\Services\Transaction;

use App\Models\Transaction;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    private TransactionQueryService $queryService;
    private TransactionConverterService $converterService;

    public function __construct()
    {
        $this->queryService = new TransactionQueryService();
        $this->converterService = new TransactionConverterService();
    }

    /**
     * Получить транзакции с фильтрацией
     */
    public function getTransactions(int $userId, array $filters, bool $includeAnomalies = false, ?int $limit = 50, bool $fetchAll = false): array
    {
        $transactions = $this->queryService->getTransactions($userId, $filters, $includeAnomalies, $limit, $fetchAll);
        $transactionsWithRates = $this->converterService->addExchangeRates($transactions);

        return [
            'data' => $transactionsWithRates,
            'meta' => [
                'total' => $transactions->count(),
                'limit' => $fetchAll ? null : $limit,
                'fetch_all' => $fetchAll,
                'include_anomalies' => $includeAnomalies
            ]
        ];
    }

    /**
     * Получить последние транзакции
     */
    public function getRecentTransactions(int $userId, int $limit = 10, bool $includeAnomalies = false): array
    {
        $transactions = $this->queryService->getRecentTransactions($userId, $limit, $includeAnomalies);
        $transactionsWithRates = $this->converterService->addExchangeRates($transactions);

        return [
            'data' => $transactionsWithRates,
            'meta' => ['include_anomalies' => $includeAnomalies]
        ];
    }

    /**
     * Получить сводку по месяцу
     */
    public function getMonthlySummary(int $userId, int $month, int $year, bool $excludeAnomalies = true): array
    {
        return $this->queryService->getMonthlySummary($userId, $month, $year, $excludeAnomalies);
    }

    /**
     * Получить транзакцию по ID
     */
    public function getTransaction(int $userId, int $id): ?Transaction
    {
        return Transaction::where('user_id', $userId)
            ->with(['categories', 'currency'])
            ->find($id);
    }

    /**
     * Создать транзакцию
     */
    public function createTransaction(int $userId, array $data): Transaction
    {
        $categoryIds = $data['category_ids'];
        unset($data['category_ids']);

        $data['user_id'] = $userId;
        $data['is_anomaly'] = $data['is_anomaly'] ?? false;

        $transaction = Transaction::create($data);
        $transaction->categories()->attach($categoryIds);

        return $transaction->load(['categories', 'currency']);
    }

    /**
     * Обновить транзакцию
     */
    public function updateTransaction(Transaction $transaction, array $data): Transaction
    {
        if (isset($data['category_ids'])) {
            $categoryIds = $data['category_ids'];
            unset($data['category_ids']);
            $transaction->categories()->sync($categoryIds);
        }

        $transaction->update($data);
        return $transaction->load(['categories', 'currency']);
    }

    /**
     * Удалить транзакцию
     */
    public function deleteTransaction(Transaction $transaction): bool
    {
        $transaction->categories()->detach();
        return $transaction->delete();
    }

    /**
     * Массовое удаление транзакций
     */
    public function massDeleteTransactions(int $userId, array $transactionIds): int
    {
        // Проверяем принадлежность всех транзакций пользователю
        $count = Transaction::where('user_id', $userId)
            ->whereIn('id', $transactionIds)
            ->count();

        if ($count !== count($transactionIds)) {
            throw new \RuntimeException('Some transactions do not belong to you');
        }

        // Удаляем связи с категориями
        foreach ($transactionIds as $id) {
            $transaction = Transaction::find($id);
            if ($transaction) {
                $transaction->categories()->detach();
            }
        }

        // Удаляем транзакции
        return Transaction::where('user_id', $userId)
            ->whereIn('id', $transactionIds)
            ->delete();
    }

    /**
     * Отметить транзакцию как аномалию
     */
    public function markAsAnomaly(Transaction $transaction, bool $isAnomaly, ?string $reason = null): Transaction
    {
        $transaction->is_anomaly = $isAnomaly;

        if ($isAnomaly && $reason) {
            $transaction->description = $transaction->description . " [Аномалия: {$reason}]";
        }

        $transaction->save();
        return $transaction->load(['categories', 'currency']);
    }

    /**
     * Получить аномальные транзакции
     */
    public function getAnomalies(int $userId, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = Transaction::where('user_id', $userId)
            ->where('is_anomaly', true)
            ->with(['categories', 'currency']);

        if ($startDate) {
            $query->where('date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('date', '<=', $endDate);
        }

        $anomalies = $query->orderBy('date', 'desc')->get();

        return [
            'data' => $anomalies,
            'meta' => [
                'total' => $anomalies->count(),
                'total_amount' => $anomalies->sum('amount')
            ]
        ];
    }
}