<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;

class TransactionCategorizer
{
    private $userId;
    private $categories = [];
    private $wordProbabilities = [];
    private $totalWords = 0;

    private $stopWords = ['на', 'за', 'в', 'для', 'с', 'по', 'к', 'у', 'о', 'об', 'и', 'а', 'но', 'или', 'же'];

    public function __construct($userId)
    {
        $this->userId = $userId;
        $this->loadTrainingData();
    }

    private function loadTrainingData()
    {
        $cacheKey = "user_category_model_{$this->userId}";

        $cached = Cache::get($cacheKey);
        if ($cached) {
            $this->categories = $cached['categories'];
            $this->wordProbabilities = $cached['wordProbabilities'];
            $this->totalWords = $cached['totalWords'];
            return;
        }

        // Получаем транзакции пользователя за последние 12 месяцев
        $transactions = Transaction::where('user_id', $this->userId)
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->where('date', '>=', now()->subMonths(12))
            ->with('categories')
            ->get();

        $categoryWords = [];
        $this->totalWords = 0;

        foreach ($transactions as $transaction) {
            foreach ($transaction->categories as $category) {
                $catId = $category->id;
                $catName = $category->name;
                $catType = $category->type;

                if (!isset($categoryWords[$catId])) {
                    $categoryWords[$catId] = [
                        'name' => $catName,
                        'type' => $catType,
                        'words' => [],
                        'count' => 0
                    ];
                }

                $words = $this->tokenize($transaction->description);
                foreach ($words as $word) {
                    if (!isset($categoryWords[$catId]['words'][$word])) {
                        $categoryWords[$catId]['words'][$word] = 0;
                    }
                    $categoryWords[$catId]['words'][$word]++;
                    $categoryWords[$catId]['count']++;
                    $this->totalWords++;
                }
            }
        }

        foreach ($categoryWords as $catId => $data) {
            $this->categories[$catId] = [
                'name' => $data['name'],
                'type' => $data['type']
            ];

            $wordCount = $data['count'];
            $vocabularySize = count($data['words']);

            foreach ($data['words'] as $word => $freq) {
                $this->wordProbabilities[$catId][$word] = ($freq + 1) / ($wordCount + $this->totalWords + $vocabularySize);
            }
        }

        // Кэш на 24 часа
        Cache::put($cacheKey, [
            'categories' => $this->categories,
            'wordProbabilities' => $this->wordProbabilities,
            'totalWords' => $this->totalWords
        ], 86400);
    }

    private function tokenize($text)
    {
        $text = mb_strtolower($text);
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $words = preg_split('/\s+/', $text);

        $words = array_filter($words, function($word) {
            return mb_strlen($word) > 2 && !in_array($word, $this->stopWords);
        });

        return array_values($words);
    }

    public function predict($description, $type = null)
    {
        if (empty($description) || empty($this->categories)) {
            return null;
        }

        $words = $this->tokenize($description);
        if (empty($words)) {
            return null;
        }

        $scores = [];

        foreach ($this->categories as $catId => $catData) {
            if ($type && $catData['type'] !== $type) {
                continue;
            }

            $score = 0;
            foreach ($words as $word) {
                if (isset($this->wordProbabilities[$catId][$word])) {
                    $score += log($this->wordProbabilities[$catId][$word]);
                } else {
                    $score += log(1 / ($this->totalWords + 1000));
                }
            }
            $scores[$catId] = $score;
        }

        if (empty($scores)) {
            return null;
        }

        arsort($scores);
        $bestCategoryId = key($scores);

        $maxScore = max($scores);
        $secondScore = count($scores) > 1 ? $scores[array_keys($scores)[1]] : $maxScore;
        $confidence = min(100, max(0, round(($maxScore - $secondScore) / (abs($maxScore) + 1) * 100)));

        return [
            'category_id' => $bestCategoryId,
            'category_name' => $this->categories[$bestCategoryId]['name'],
            'category_type' => $this->categories[$bestCategoryId]['type'],
            'confidence' => $confidence
        ];
    }

    public function suggest($description, $type = null)
    {
        $prediction = $this->predict($description, $type);

        if ($prediction && $prediction['confidence'] > 40) {
            return $prediction;
        }

        return null;
    }
}