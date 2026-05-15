<?php

namespace App\Services\Category;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CategoryValidationService
{
    /**
     * Валидация для создания категории
     */
    public function validateStore(array $data, int $userId): array
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense',
            'color' => 'nullable|string|max:7',
            'budget_limit' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) use ($data) {
                    if (($data['type'] ?? 'expense') === 'expense' && $value === null) {
                        $fail('Для категории расходов необходимо указать лимит бюджета.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        // Дополнительная проверка: категория с таким именем уже существует
        $exists = Category::where('user_id', $userId)
            ->where('name', $data['name'])
            ->exists();

        if ($exists) {
            throw new \InvalidArgumentException('Категория с таким именем уже существует.');
        }

        return $validator->validated();
    }

    /**
     * Валидация для обновления категории
     */
    public function validateUpdate(array $data, int $userId, int $categoryId): array
    {
        $category = Category::where('user_id', $userId)->find($categoryId);

        $validator = Validator::make($data, [
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:income,expense',
            'color' => 'nullable|string|max:7',
            'budget_limit' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) use ($data, $category) {
                    $type = $data['type'] ?? $category->type;

                    if ($type === 'expense' && $value === null) {
                        $fail('Для категории расходов необходимо указать лимит бюджета.');
                    }

                    if ($type === 'income' && $value !== null) {
                        $fail('Лимит бюджета нельзя указывать для доходов.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        // Проверка на дубликат имени
        if (isset($data['name'])) {
            $exists = Category::where('user_id', $userId)
                ->where('name', $data['name'])
                ->where('id', '!=', $categoryId)
                ->exists();

            if ($exists) {
                throw new \InvalidArgumentException('Категория с таким именем уже существует.');
            }
        }

        return $validator->validated();
    }
}