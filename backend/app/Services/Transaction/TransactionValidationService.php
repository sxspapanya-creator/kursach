<?php

namespace App\Services\Transaction;

use App\Models\Category;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TransactionValidationService
{
    /**
     * Валидация для списка транзакций
     */
    public function validateIndex(array $data, int $userId): array
    {
        $validator = Validator::make($data, [
            'type' => 'nullable|in:income,expense',
            'category_ids' => 'nullable|array',
            'category_ids.*' => [
                'integer',
                Rule::exists('categories', 'id')->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'limit' => 'nullable|integer|min:1|max:10000',
            'include_anomalies' => 'nullable|in:true,false,1,0',
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Валидация для создания транзакции
     */
    public function validateStore(array $data, int $userId): array
    {
        $validator = Validator::make($data, [
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:income,expense',
            'category_ids' => 'required|array|min:1',
            'category_ids.*' => 'integer|exists:categories,id',
            'description' => 'nullable|string|max:500',
            'date' => 'required|date',
            'payment_method' => 'required|in:cash,card,transfer',
            'currency_id' => 'required|exists:currencies,id',
            'is_anomaly' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $validated = $validator->validated();

        // Проверяем, что все категории принадлежат пользователю
        $categoryIds = $validated['category_ids'];
        $validCategoryIds = Category::where('user_id', $userId)
            ->whereIn('id', $categoryIds)
            ->pluck('id')
            ->toArray();

        if (count($validCategoryIds) !== count($categoryIds)) {
            throw new \InvalidArgumentException('Одна или несколько категорий не найдены или не принадлежат вам.');
        }

        return $validated;
    }

    /**
     * Валидация для обновления транзакции
     */
    public function validateUpdate(array $data): array
    {
        $validator = Validator::make($data, [
            'amount' => 'sometimes|required|numeric|min:0.01',
            'type' => 'sometimes|required|in:income,expense',
            'category_ids' => 'sometimes|required|array|min:1',
            'category_ids.*' => 'integer|exists:categories,id',
            'description' => 'nullable|string|max:500',
            'date' => 'sometimes|required|date',
            'payment_method' => 'sometimes|required|in:cash,card,transfer',
            'currency_id' => 'sometimes|required|exists:currencies,id',
            'is_anomaly' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Валидация для массового удаления
     */
    public function validateMassDelete(array $data): array
    {
        $validator = Validator::make($data, [
            'transaction_ids' => 'required|array',
            'transaction_ids.*' => 'integer|exists:transactions,id'
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Валидация для отметки аномалии
     */
    public function validateMarkAnomaly(array $data): array
    {
        $validator = Validator::make($data, [
            'is_anomaly' => 'required|boolean',
            'reason' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Валидация для получения аномалий
     */
    public function validateGetAnomalies(array $data): array
    {
        $validator = Validator::make($data, [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Валидация для сводки
     */
    public function validateSummary(array $data): array
    {
        $validator = Validator::make($data, [
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
            'exclude_anomalies' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * Валидация для последних транзакций
     */
    public function validateRecent(array $data): array
    {
        $validator = Validator::make($data, [
            'limit' => 'nullable|integer|min:1|max:1000',
            'include_anomalies' => 'nullable|in:true,false,0,1'
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        return $validator->validated();
    }
}