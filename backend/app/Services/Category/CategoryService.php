<?php

namespace App\Services\Category;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\Currency;
use App\Services\Analytics\CurrencyConverterService;
use Illuminate\Support\Facades\Auth;

class CategoryService
{
    private CurrencyConverterService $currencyConverter;

    public function __construct(CurrencyConverterService $currencyConverter)
    {
        $this->currencyConverter = $currencyConverter;
    }

    /**
     * Получить все категории пользователя со статистикой
     */
    public function getAllCategories(int $userId): array
    {
        $categories = Category::where('user_id', $userId)
            ->withCount(['transactions as transactions_count'])
            ->withSum(['transactions as transactions_amount'], 'amount')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        return $categories->toArray();
    }

    /**
     * Получить категорию по ID
     */
    public function getCategory(int $userId, int $id): ?Category
    {
        return Category::where('user_id', $userId)
            ->withCount(['transactions as transactions_count'])
            ->withSum(['transactions as transactions_amount'], 'amount')
            ->find($id);
    }

    /**
     * Создать категорию
     */
    public function createCategory(int $userId, array $data): Category
    {
        $data['user_id'] = $userId;

        // Для доходов лимит бюджета всегда null
        if ($data['type'] === 'income') {
            $data['budget_limit'] = null;
        }

        $category = Category::create($data);
        $category->transactions_count = 0;
        $category->transactions_amount = 0;

        return $category;
    }

    /**
     * Обновить категорию
     */
    public function updateCategory(Category $category, array $data): Category
    {
        // Если тип меняется на доход, сбрасываем лимит
        if (($data['type'] ?? $category->type) === 'income') {
            $data['budget_limit'] = null;
        }

        $category->update($data);

        $category->loadCount(['transactions as transactions_count']);
        $category->loadSum(['transactions as transactions_amount'], 'amount');

        return $category;
    }

    /**
     * Удалить категорию (проверка на наличие транзакций)
     */
    public function deleteCategory(Category $category): bool
    {
        $transactionCount = $category->transactions()->count();

        if ($transactionCount > 0) {
            throw new \RuntimeException('Cannot delete category with existing transactions');
        }

        return $category->delete();
    }

    /**
     * Проверить существование категории с таким именем
     */
    public function categoryNameExists(int $userId, string $name, ?int $excludeId = null): bool
    {
        $query = Category::where('user_id', $userId)->where('name', $name);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}