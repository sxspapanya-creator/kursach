<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    /**
     * Получить ID текущего пользователя
     */
    protected function getUserId()
    {
        $userId = Auth::id();
        if (!$userId) {
            abort(401, 'Unauthorized');
        }
        return $userId;
    }
    /**
     * Общий обзор аналитики
     */
    public function overview(Request $request)
    {
        try {
            $userId = $this->getUserId();
            
            $validated = $request->validate([
                'period' => 'nullable|in:week,month,year',
                'year' => 'nullable|integer|min:2000|max:2100',
                'month' => 'nullable|integer|min:1|max:12'
            ]);
            
            // Параметры периода
            $period = $validated['period'] ?? 'month';
            $year = $validated['year'] ?? date('Y');
            $month = $validated['month'] ?? date('m');

            // Базовые данные
            $baseData = $this->getBaseAnalytics($year, $month, $period, $userId);

            // Прогнозы с линейной регрессией
            $forecasts = $this->calculateForecasts($userId);

            // Тренды с взвешенным скользящим средним
            $trends = $this->calculateTrends($userId);

            // Финансовое здоровье
            $financialHealth = $this->calculateFinancialHealth(
                $baseData['totals']['balance'] ?? 0,
                $baseData['totals']['savings_rate'] ?? 0
            );

            // Рекомендации с прогнозом кассового разрыва
            $recommendations = $this->generateRecommendations(
                $baseData['totals'] ?? [],
                $forecasts,
                $trends
            );

            // Крупнейшие транзакции
            $largestTransactions = $this->getLargestTransactions($year, $month, $userId);

            // Расходы по категориям с анализом
            $categorySpending = $this->getCategorySpendingWithAnalysis($year, $month);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'totals' => $baseData['totals'] ?? [],
                    'date_range' => $baseData['date_range'] ?? [],
                    'category_spending' => $categorySpending,
                    'recommendations' => $recommendations,
                    'forecasts' => $forecasts,
                    'trends' => $trends,
                    'financial_health' => $financialHealth,
                    'largest_transactions' => $largestTransactions,
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Analytics overview error: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());

            return response()->json([
                'status' => 'error',
                'message' => 'Ошибка при расчете аналитики: ' . $e->getMessage(),
                'data' => [
                    'totals' => [
                        'income' => 0,
                        'expenses' => 0,
                        'balance' => 0,
                        'savings_rate' => 0,
                    ],
                    'date_range' => [
                        'start' => now()->format('Y-m-d'),
                        'end' => now()->format('Y-m-d'),
                        'label' => 'Нет данных'
                    ],
                    'category_spending' => [],
                    'recommendations' => [],
                    'forecasts' => [
                        'income_regression' => [
                            'a' => 0,
                            'b' => 0,
                            'r_squared' => 0,
                            'trend' => 'stable',
                            'next_month' => 0
                        ],
                        'expense_regression' => [
                            'a' => 0,
                            'b' => 0,
                            'r_squared' => 0,
                            'trend' => 'stable',
                            'next_month' => 0
                        ],
                        'next_month_income' => 0,
                        'next_month_expense' => 0,
                        'optimal_distribution' => []
                    ],
                    'trends' => [
                        'weighted_moving_average' => [],
                        'actual_data' => [],
                        'trend_direction' => 'stable'
                    ],
                    'financial_health' => [
                        'score' => 0,
                        'status' => 'poor',
                        'status_label' => 'Нет данных',
                        'color' => '#95a5a6'
                    ],
                    'largest_transactions' => [
                        'expenses' => [],
                        'incomes' => []
                    ]
                ]
            ], 200); // Возвращаем 200 с данными по умолчанию вместо 500
        }
    }

    /**
     * Базовые показатели аналитики
     */
    private function getBaseAnalytics($year, $month, $period = 'month', $userId = null)
    {
        try {
            if (!$userId) {
                $userId = $this->getUserId();
            }
            
            $startDate = null;
            $endDate = null;
            $label = '';

            // Определяем даты в зависимости от периода
            switch ($period) {
                case 'week':
                    // Текущая неделя
                    $startDate = Carbon::now()->startOfWeek();
                    $endDate = Carbon::now()->endOfWeek();
                    $label = 'Неделя ' . $startDate->format('d.m.Y') . ' - ' . $endDate->format('d.m.Y');
                    break;

                case 'year':
                    // Год из параметров
                    $startDate = Carbon::create($year, 1, 1)->startOfYear();
                    $endDate = Carbon::create($year, 12, 31)->endOfYear();
                    $label = 'Год ' . $year;
                    break;

                case 'month':
                default:
                    // Месяц из параметров
                    $startDate = Carbon::create($year, $month, 1)->startOfMonth();
                    $endDate = $startDate->copy()->endOfMonth();
                    $label = $startDate->translatedFormat('F Y');
                    break;
            }

            // Доходы и расходы за период
            $transactions = Transaction::where('user_id', $userId)
                ->whereBetween('date', [$startDate, $endDate])
                ->selectRaw('SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as total_income')
                ->selectRaw('SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as total_expense')
                ->first();

            $totalIncome = $transactions->total_income ?? 0;
            $totalExpense = $transactions->total_expense ?? 0;
            $balance = $totalIncome - $totalExpense;

            // Норма сбережений
            $savingsRate = $totalIncome > 0 ? ($balance / $totalIncome) * 100 : 0;

            return [
                'totals' => [
                    'income' => (float) $totalIncome,
                    'expenses' => (float) $totalExpense,
                    'balance' => (float) $balance,
                    'savings_rate' => (float) $savingsRate,
                ],
                'date_range' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                    'label' => $label
                ]
            ];
        } catch (\Exception $e) {
            \Log::error('Base analytics error: ' . $e->getMessage());
            return [
                'totals' => [
                    'income' => 0,
                    'expenses' => 0,
                    'balance' => 0,
                    'savings_rate' => 0,
                ],
                'date_range' => [
                    'start' => now()->format('Y-m-d'),
                    'end' => now()->format('Y-m-d'),
                    'label' => 'Ошибка расчета'
                ]
            ];
        }
    }

    /**
     * Линейная регрессия для прогноза
     * y = a + b*x
     */
    private function linearRegression($data)
    {
        $n = count($data);
        if ($n < 2) {
            return [
                'a' => 0,
                'b' => 0,
                'r_squared' => 0,
                'trend' => 'stable',
                'next_month' => 0
            ];
        }

        // Переиндексируем x от 1 до n
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;
        $sumY2 = 0;

        $i = 1;
        foreach ($data as $value) {
            $sumX += $i;
            $sumY += $value;
            $sumXY += $i * $value;
            $sumX2 += $i * $i;
            $sumY2 += $value * $value;
            $i++;
        }

        // Коэффициенты регрессии
        $denominator = ($n * $sumX2 - $sumX * $sumX);
        if ($denominator == 0) {
            return [
                'a' => 0,
                'b' => 0,
                'r_squared' => 0,
                'trend' => 'stable',
                'next_month' => 0
            ];
        }

        $b = ($n * $sumXY - $sumX * $sumY) / $denominator;
        $a = ($sumY - $b * $sumX) / $n;

        // Коэффициент детерминации R²
        $ssr = 0; // Объясненная сумма квадратов
        $sst = 0; // Общая сумма квадратов
        $meanY = $sumY / $n;

        $i = 1;
        foreach ($data as $value) {
            $predicted = $a + $b * $i;
            $ssr += pow($predicted - $meanY, 2);
            $sst += pow($value - $meanY, 2);
            $i++;
        }

        $rSquared = $sst > 0 ? $ssr / $sst : 0;

        // Определение тренда
        $trend = 'stable';
        if ($b > 100) $trend = 'growth';
        elseif ($b < -100) $trend = 'decline';

        return [
            'a' => $a,
            'b' => $b,
            'r_squared' => $rSquared,
            'trend' => $trend,
            'next_month' => $a + $b * ($n + 1)
        ];
    }

    /**
     * Прогнозы доходов и расходов
     */
    private function calculateForecasts($userId = null)
    {
        try {
            if (!$userId) {
                $userId = $this->getUserId();
            }
            
            // Получаем исторические данные за последние 12 месяцев
            $endDate = Carbon::now()->endOfMonth();
            $startDate = $endDate->copy()->subMonths(12)->startOfMonth();

            // Доходы по месяцам
            $monthlyIncome = Transaction::where('user_id', $userId)
                ->whereBetween('date', [$startDate, $endDate])
                ->where('type', 'income')
                ->selectRaw('YEAR(date) as year, MONTH(date) as month, SUM(amount) as total')
                ->groupBy(DB::raw('YEAR(date), MONTH(date)'))
                ->orderBy('year', 'asc')
                ->orderBy('month', 'asc')
                ->get()
                ->pluck('total')
                ->toArray();

            // Если данных недостаточно, возвращаем нулевые значения
            if (count($monthlyIncome) < 2) {
                $incomeRegression = [
                    'a' => 0,
                    'b' => 0,
                    'r_squared' => 0,
                    'trend' => 'stable',
                    'next_month' => 0
                ];
            } else {
                // Линейная регрессия для доходов
                $incomeRegression = $this->linearRegression($monthlyIncome);
            }

            // Расходы по месяцам
            $monthlyExpense = Transaction::where('user_id', $userId)
                ->whereBetween('date', [$startDate, $endDate])
                ->where('type', 'expense')
                ->selectRaw('YEAR(date) as year, MONTH(date) as month, SUM(amount) as total')
                ->groupBy(DB::raw('YEAR(date), MONTH(date)'))
                ->orderBy('year', 'asc')
                ->orderBy('month', 'asc')
                ->get()
                ->pluck('total')
                ->toArray();

            // Если данных недостаточно, возвращаем нулевые значения
            if (count($monthlyExpense) < 2) {
                $expenseRegression = [
                    'a' => 0,
                    'b' => 0,
                    'r_squared' => 0,
                    'trend' => 'stable',
                    'next_month' => 0
                ];
            } else {
                // Линейная регрессия для расходов
                $expenseRegression = $this->linearRegression($monthlyExpense);
            }

            // Оптимальное распределение для категорий расходов
            $optimalDistribution = $this->calculateOptimalDistribution($userId);

            return [
                'income_regression' => $incomeRegression,
                'expense_regression' => $expenseRegression,
                'next_month_income' => max(0, $incomeRegression['next_month']),
                'next_month_expense' => max(0, $expenseRegression['next_month']),
                'optimal_distribution' => $optimalDistribution,
            ];
        } catch (\Exception $e) {
            \Log::error('Forecasts calculation error: ' . $e->getMessage());
            return [
                'income_regression' => [
                    'a' => 0,
                    'b' => 0,
                    'r_squared' => 0,
                    'trend' => 'stable',
                    'next_month' => 0
                ],
                'expense_regression' => [
                    'a' => 0,
                    'b' => 0,
                    'r_squared' => 0,
                    'trend' => 'stable',
                    'next_month' => 0
                ],
                'next_month_income' => 0,
                'next_month_expense' => 0,
                'optimal_distribution' => []
            ];
        }
    }

    /**
     * Взвешенное скользящее среднее
     */
    private function weightedMovingAverage($data, $periods = 6)
    {
        $n = count($data);
        if ($n < $periods) return $data; // Возвращаем оригинальные данные если недостаточно

        $result = [];

        for ($i = $periods - 1; $i < $n; $i++) {
            $sum = 0;
            $weightSum = 0;

            // Веса увеличиваются линейно для более новых данных
            for ($j = 0; $j < $periods; $j++) {
                $weight = $j + 1; // Вес от 1 до periods
                $sum += $data[$i - $periods + 1 + $j] * $weight;
                $weightSum += $weight;
            }

            $result[] = $sum / $weightSum;
        }

        return $result;
    }

    /**
     * Расчет трендов
     */
    private function calculateTrends($userId = null)
    {
        try {
            if (!$userId) {
                $userId = $this->getUserId();
            }
            
            // Получаем данные за последние 12 месяцев
            $endDate = Carbon::now()->endOfMonth();
            $startDate = $endDate->copy()->subMonths(12)->startOfMonth();

            // Фактические расходы по месяцам
            $monthlyData = Transaction::where('user_id', $userId)
                ->whereBetween('date', [$startDate, $endDate])
                ->where('type', 'expense')
                ->selectRaw('YEAR(date) as year, MONTH(date) as month, SUM(amount) as total')
                ->groupBy(DB::raw('YEAR(date), MONTH(date)'))
                ->orderBy('year', 'asc')
                ->orderBy('month', 'asc')
                ->get();

            if ($monthlyData->isEmpty()) {
                // Если нет данных, возвращаем пустые массивы
                return [
                    'weighted_moving_average' => [],
                    'trend_direction' => 'stable',
                    'actual_data' => []
                ];
            }

            $actualData = $monthlyData->pluck('total')->toArray();
            $months = $monthlyData->map(function($item) {
                return $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT);
            })->toArray();

            // Взвешенное скользящее среднее
            $weightedAverage = $this->weightedMovingAverage($actualData, 3);

            // Подготавливаем данные для ответа
            $weightedData = [];
            $startIndex = count($actualData) - count($weightedAverage);

            for ($i = 0; $i < count($weightedAverage); $i++) {
                $monthIndex = $startIndex + $i;
                $weightedData[] = [
                    'month' => $months[$monthIndex] ?? Carbon::now()->subMonths(count($actualData) - $monthIndex - 1)->format('Y-m'),
                    'actual' => $actualData[$monthIndex] ?? 0,
                    'weighted_average' => $weightedAverage[$i] ?? 0
                ];
            }

            // Определение направления тренда
            $trendDirection = 'stable';
            if (count($weightedAverage) >= 2) {
                $last = end($weightedAverage);
                $prev = $weightedAverage[count($weightedAverage) - 2];
                if ($prev > 0) {
                    $change = (($last - $prev) / $prev) * 100;

                    if ($change > 5) $trendDirection = 'growth';
                    elseif ($change < -5) $trendDirection = 'decline';
                }
            }

            return [
                'weighted_moving_average' => $weightedData,
                'trend_direction' => $trendDirection,
                'actual_data' => array_slice($actualData, -6), // Последние 6 месяцев для линейного графика
            ];
        } catch (\Exception $e) {
            \Log::error('Trends calculation error: ' . $e->getMessage());
            return [
                'weighted_moving_average' => [],
                'trend_direction' => 'stable',
                'actual_data' => []
            ];
        }
    }

    /**
     * Расчет финансового здоровья
     */
    private function calculateFinancialHealth($balance, $savingsRate)
    {
        try {
            $score = 0;

            // Оценка по балансу (макс 50 баллов)
            if ($balance > 0) {
                $score += min(50, ($balance / 10000) * 10);
            }

            // Оценка по норме сбережений (макс 50 баллов)
            if ($savingsRate > 0) {
                $score += min(50, $savingsRate * 2);
            }

            $score = min(100, max(0, $score));

            // Определение статуса
            if ($score >= 80) {
                $status = 'excellent';
                $statusLabel = 'Отлично';
                $color = '#27ae60';
            } elseif ($score >= 60) {
                $status = 'good';
                $statusLabel = 'Хорошо';
                $color = '#2ecc71';
            } elseif ($score >= 40) {
                $status = 'fair';
                $statusLabel = 'Удовлетворительно';
                $color = '#f39c12';
            } elseif ($score >= 20) {
                $status = 'poor';
                $statusLabel = 'Плохо';
                $color = '#e74c3c';
            } else {
                $status = 'critical';
                $statusLabel = 'Критично';
                $color = '#c0392b';
            }

            return [
                'score' => round($score),
                'status' => $status,
                'status_label' => $statusLabel,
                'color' => $color
            ];
        } catch (\Exception $e) {
            \Log::error('Financial health calculation error: ' . $e->getMessage());
            return [
                'score' => 0,
                'status' => 'poor',
                'status_label' => 'Не определено',
                'color' => '#95a5a6'
            ];
        }
    }

    /**
     * Прогноз кассового разрыва и рекомендации
     */
    private function generateRecommendations($totals, $forecasts, $trends)
    {
        $recommendations = [];

        try {
            // Текущие показатели
            $currentBalance = $totals['balance'] ?? 0;
            $currentExpenses = $totals['expenses'] ?? 0;
            $currentIncome = $totals['income'] ?? 0;

            // Прогноз на следующий месяц
            $forecastedIncome = $forecasts['next_month_income'] ?? 0;
            $forecastedExpense = $forecasts['next_month_expense'] ?? 0;
            
            // Проверяем, есть ли достаточно данных для прогноза
            $hasForecastData = ($forecastedIncome > 0 || $forecastedExpense > 0) && 
                               ($forecasts['income_regression']['r_squared'] > 0 || $forecasts['expense_regression']['r_squared'] > 0);

            // Прогноз кассового разрыва (только если есть данные для прогноза)
            if ($hasForecastData) {
                $cashGapForecast = $currentBalance + $forecastedIncome - $forecastedExpense;

                // Анализ кассового разрыва
                if ($cashGapForecast < 0) {
                    $recommendations[] = [
                        'type' => 'critical',
                        'title' => '⚠️ Прогнозируется кассовый разрыв',
                        'message' => 'По прогнозам, в следующем месяце будет отрицательный баланс на ' .
                            number_format(abs($cashGapForecast), 0, ',', ' ') . ' Br. Рекомендуем сократить расходы.'
                    ];
                } elseif ($cashGapForecast < $currentExpenses * 0.3 && $currentExpenses > 0) {
                    $recommendations[] = [
                        'type' => 'warning',
                        'title' => '🔔 Маленький запас прочности',
                        'message' => 'Прогнозируемый остаток составляет менее 30% от текущих расходов. ' .
                            'Рекомендуем увеличить сбережения.'
                    ];
                }
            }

            // Анализ тренда расходов (только если есть данные)
            $trend = $trends['trend_direction'] ?? 'stable';
            $hasTrendData = !empty($trends['actual_data']);
            
            if ($hasTrendData && $trend === 'growth') {
                $recommendations[] = [
                    'type' => 'warning',
                    'title' => '📈 Рост расходов',
                    'message' => 'Наблюдается тенденция к увеличению расходов. ' .
                        'Рекомендуем проанализировать статьи затрат.'
                ];
            }

            // Анализ нормы сбережений (только если есть данные)
            if ($currentIncome > 0) {
                $savingsRate = $totals['savings_rate'] ?? 0;
                if ($savingsRate < 10 && $savingsRate > 0) {
                    $recommendations[] = [
                        'type' => 'warning',
                        'title' => '💰 Низкая норма сбережений',
                        'message' => 'Ваша норма сбережений составляет ' . round($savingsRate, 1) .
                            '%. Рекомендуемый минимум - 10-15%.'
                    ];
                } elseif ($savingsRate > 30) {
                    $recommendations[] = [
                        'type' => 'success',
                        'title' => '✅ Отличная норма сбережений',
                        'message' => 'Ваша норма сбережений ' . round($savingsRate, 1) .
                            '% выше рекомендуемой. Отличная работа!'
                    ];
                }
            }

            // Анализ крупных расходов (только если есть данные)
            if ($currentExpenses > 0 && $currentIncome > 0 && $currentExpenses > $currentIncome * 0.9) {
                $recommendations[] = [
                    'type' => 'critical',
                    'title' => '💸 Высокий уровень расходов',
                    'message' => 'Расходы составляют более 90% от доходов. ' .
                        'Рекомендуем срочно оптимизировать бюджет.'
                ];
            }

            // Положительные рекомендации при хороших показателях (только если есть данные)
            if ($currentIncome > 0 || $currentExpenses > 0) {
                if (empty($recommendations) || count(array_filter($recommendations, fn($r) => $r['type'] === 'success')) > 0) {
                    $recommendations[] = [
                        'type' => 'success',
                        'title' => '🎯 Бюджет под контролем',
                        'message' => 'Ваши финансовые показатели выглядят стабильно. Продолжайте следить за бюджетом!'
                    ];
                }
            } else {
                // Если нет данных вообще, показываем информационное сообщение
                $recommendations[] = [
                    'type' => 'info',
                    'title' => '📊 Добавьте данные',
                    'message' => 'Для получения аналитики и рекомендаций добавьте транзакции и категории.'
                ];
            }

        } catch (\Exception $e) {
            \Log::error('Recommendations generation error: ' . $e->getMessage());
            $recommendations[] = [
                'type' => 'success',
                'title' => '📊 Аналитика загружена',
                'message' => 'Система аналитики успешно запущена. Добавьте данные для получения рекомендаций.'
            ];
        }

        return $recommendations;
    }

    /**
     * Поиск оптимального распределения для категорий расходов
     */
    private function calculateOptimalDistribution($userId = null)
    {
        try {
            if (!$userId) {
                $userId = $this->getUserId();
            }
            
            // Получаем категории расходов (type = 'expense')
            $categories = Category::where('user_id', $userId)
                ->where('type', 'expense')
                ->whereHas('transactions', function($query) use ($userId) {
                    $query->where('user_id', $userId)
                        ->where('type', 'expense');
                })
                ->with(['transactions' => function($query) use ($userId) {
                    $query->where('user_id', $userId)
                        ->where('type', 'expense')
                        ->where('date', '>=', Carbon::now()->subMonths(6));
                }])
                ->get();

            $distribution = [];

            foreach ($categories as $category) {
                $totalExpense = $category->transactions->sum('amount');
                $monthlyAverage = $totalExpense / max(1, count($category->transactions));

                // Анализ стабильности расходов (дисперсия)
                $monthlyData = [];
                for ($i = 0; $i < 6; $i++) {
                    $monthStart = Carbon::now()->subMonths($i)->startOfMonth();
                    $monthEnd = Carbon::now()->subMonths($i)->endOfMonth();

                    $monthExpense = $category->transactions
                        ->where('date', '>=', $monthStart)
                        ->where('date', '<=', $monthEnd)
                        ->sum('amount');

                    $monthlyData[] = $monthExpense;
                }

                // Коэффициент вариации для определения стабильности
                $mean = array_sum($monthlyData) / max(1, count($monthlyData));
                $variance = 0;
                foreach ($monthlyData as $value) {
                    $variance += pow($value - $mean, 2);
                }
                $stdDev = sqrt($variance / max(1, count($monthlyData)));
                $coefficientOfVariation = $mean > 0 ? ($stdDev / $mean) * 100 : 0;

                // Рекомендуемый лимит на основе стабильности
                $stabilityScore = 100 - min($coefficientOfVariation, 100);
                $recommendedLimit = $coefficientOfVariation < 30 ?
                    $monthlyAverage * 1.2 : // Стабильные расходы +20%
                    $monthlyAverage * 1.5;  // Нестабильные расходы +50%

                $distribution[] = [
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'current_monthly_avg' => $monthlyAverage,
                    'stability_score' => $stabilityScore,
                    'recommended_limit' => round($recommendedLimit),
                    'coefficient_of_variation' => round($coefficientOfVariation, 1)
                ];
            }

            // Сортируем по recommended_limit (по убыванию)
            usort($distribution, function($a, $b) {
                return $b['recommended_limit'] <=> $a['recommended_limit'];
            });

            return $distribution;
        } catch (\Exception $e) {
            \Log::error('Optimal distribution calculation error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Крупнейшие транзакции
     */
    private function getLargestTransactions($year, $month, $userId = null)
    {
        try {
            if (!$userId) {
                $userId = $this->getUserId();
            }
            
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            $largestExpenses = Transaction::where('user_id', $userId)
                ->with('category')
                ->whereBetween('date', [$startDate, $endDate])
                ->where('type', 'expense')
                ->orderBy('amount', 'desc')
                ->limit(5)
                ->get()
                ->map(function($transaction) {
                    return [
                        'id' => $transaction->id,
                        'description' => $transaction->description ?? 'Без описания',
                        'amount' => $transaction->amount,
                        'date' => $transaction->date->format('d.m.Y'),
                        'category' => $transaction->category->name ?? 'Без категории',
                        'category_color' => $transaction->category->color ?? '#95a5a6'
                    ];
                });

            $largestIncomes = Transaction::where('user_id', $userId)
                ->with('category')
                ->whereBetween('date', [$startDate, $endDate])
                ->where('type', 'income')
                ->orderBy('amount', 'desc')
                ->limit(5)
                ->get()
                ->map(function($transaction) {
                    return [
                        'id' => $transaction->id,
                        'description' => $transaction->description ?? 'Без описания',
                        'amount' => $transaction->amount,
                        'date' => $transaction->date->format('d.m.Y'),
                        'category' => $transaction->category->name ?? 'Без категории',
                        'category_color' => $transaction->category->color ?? '#95a5a6'
                    ];
                });

            return [
                'expenses' => $largestExpenses,
                'incomes' => $largestIncomes
            ];
        } catch (\Exception $e) {
            \Log::error('Largest transactions error: ' . $e->getMessage());
            return [
                'expenses' => [],
                'incomes' => []
            ];
        }
    }

    /**
     * Расходы по категориям с анализом
     */
    private function getCategorySpendingWithAnalysis($year, $month, $userId = null)
    {
        try {
            if (!$userId) {
                $userId = $this->getUserId();
            }
            
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            $categories = Category::where('user_id', $userId)
                ->where('type', 'expense')
                ->with(['transactions' => function($query) use ($startDate, $endDate, $userId) {
                    $query->where('user_id', $userId)
                        ->where('type', 'expense')
                        ->whereBetween('date', [$startDate, $endDate]);
                }])
                ->get()
                ->map(function($category) use ($startDate, $userId) {
                    $currentMonthTotal = $category->transactions->sum('amount');

                    // Среднемесячные расходы за последние 6 месяцев
                    $sixMonthsAgo = $startDate->copy()->subMonths(6);
                    $averageMonthly = Transaction::where('user_id', $userId)
                        ->where('category_id', $category->id)
                        ->where('type', 'expense')
                        ->where('date', '>=', $sixMonthsAgo)
                        ->where('date', '<=', $startDate)
                        ->selectRaw('COALESCE(SUM(amount) / 6, 0) as average')
                        ->value('average') ?? 0;

                    // Процент от лимита
                    $limitPercentage = $category->budget_limit > 0 ?
                        ($currentMonthTotal / $category->budget_limit) * 100 : 0;

                    // Статус бюджета
                    if ($category->budget_limit <= 0) {
                        $budgetStatus = 'no_limit';
                    } elseif ($limitPercentage >= 100) {
                        $budgetStatus = 'critical';
                    } elseif ($limitPercentage >= 80) {
                        $budgetStatus = 'warning';
                    } else {
                        $budgetStatus = 'good';
                    }

                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'color' => $category->color ?? '#3498db',
                        'total' => $currentMonthTotal,
                        'budget_limit' => $category->budget_limit,
                        'limit_percentage' => $limitPercentage,
                        'budget_status' => $budgetStatus,
                        'average_monthly' => $averageMonthly
                    ];
                })
                ->filter(fn($cat) => $cat['total'] > 0)
                ->sortByDesc('total')
                ->values()
                ->toArray();

            return $categories;
        } catch (\Exception $e) {
            \Log::error('Category spending analysis error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Анализ расходов по категориям
     */
    public function categorySpending(Request $request)
    {
        try {
            $userId = $this->getUserId();
            
            $validated = $request->validate([
                'year' => 'nullable|integer|min:2000|max:2100',
                'month' => 'nullable|integer|min:1|max:12'
            ]);
            
            $year = $validated['year'] ?? date('Y');
            $month = $validated['month'] ?? date('m');

            $categorySpending = $this->getCategorySpendingWithAnalysis($year, $month, $userId);

            return response()->json([
                'status' => 'success',
                'data' => $categorySpending
            ]);
        } catch (\Exception $e) {
            \Log::error('Category spending endpoint error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ошибка при получении данных',
                'data' => []
            ]);
        }
    }

    /**
     * Месячные тренды
     */
    public function monthlyTrends(Request $request)
    {
        try {
            $userId = $this->getUserId();
            
            $validated = $request->validate([
                'months' => 'nullable|integer|min:1|max:60'
            ]);
            
            $months = $validated['months'] ?? 12;

            $endDate = Carbon::now()->endOfMonth();
            $startDate = $endDate->copy()->subMonths($months)->startOfMonth();

            $trends = Transaction::where('user_id', $userId)
                ->whereBetween('date', [$startDate, $endDate])
                ->selectRaw('YEAR(date) as year, MONTH(date) as month')
                ->selectRaw('SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as income')
                ->selectRaw('SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as expense')
                ->groupBy(DB::raw('YEAR(date), MONTH(date)'))
                ->orderBy('year', 'asc')
                ->orderBy('month', 'asc')
                ->get()
                ->map(function($item) {
                    return [
                        'month' => $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT),
                        'income' => (float) ($item->income ?? 0),
                        'expense' => (float) ($item->expense ?? 0),
                        'balance' => (float) (($item->income ?? 0) - ($item->expense ?? 0))
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $trends
            ]);
        } catch (\Exception $e) {
            \Log::error('Monthly trends endpoint error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ошибка при получении трендов',
                'data' => []
            ]);
        }
    }

    /**
     * Сравнение категорий
     */
    public function categoryComparison(Request $request)
    {
        try {
            $userId = $this->getUserId();
            
            $validated = $request->validate([
                'year' => 'nullable|integer|min:2000|max:2100'
            ]);
            
            $year = $validated['year'] ?? date('Y');

            $comparison = Category::where('user_id', $userId)
                ->with(['transactions' => function($query) use ($year) {
                $query->where('type', 'expense')
                    ->whereYear('date', $year);
            }])
                ->get()
                ->map(function($category) use ($year) {
                    $yearlyTotal = $category->transactions->sum('amount');

                    // Помесячная разбивка
                    $monthlyBreakdown = [];
                    for ($month = 1; $month <= 12; $month++) {
                        $monthTotal = $category->transactions
                            ->filter(fn($t) => $t->date->month == $month)
                            ->sum('amount');

                        $monthlyBreakdown[] = [
                            'month' => $month,
                            'amount' => $monthTotal,
                            'percentage' => $yearlyTotal > 0 ? ($monthTotal / $yearlyTotal) * 100 : 0
                        ];
                    }

                    return [
                        'category' => $category->name,
                        'total' => $yearlyTotal,
                        'monthly_breakdown' => $monthlyBreakdown,
                        'color' => $category->color ?? '#3498db'
                    ];
                })
                ->filter(fn($cat) => $cat['total'] > 0)
                ->sortByDesc('total')
                ->values();

            return response()->json([
                'status' => 'success',
                'data' => $comparison
            ]);
        } catch (\Exception $e) {
            \Log::error('Category comparison endpoint error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ошибка при сравнении категорий',
                'data' => []
            ]);
        }
    }

    /**
     * Прогресс по бюджету
     */
    public function budgetProgress(Request $request)
    {
        try {
            $userId = $this->getUserId();
            
            $validated = $request->validate([
                'year' => 'nullable|integer|min:2000|max:2100',
                'month' => 'nullable|integer|min:1|max:12'
            ]);
            
            $year = $validated['year'] ?? date('Y');
            $month = $validated['month'] ?? date('m');

            $progress = Category::where('user_id', $userId)
                ->where('budget_limit', '>', 0)
                ->with(['transactions' => function($query) use ($year, $month) {
                    $query->where('type', 'expense')
                        ->whereYear('date', $year)
                        ->whereMonth('date', $month);
                }])
                ->get()
                ->map(function($category) {
                    $currentSpending = $category->transactions->sum('amount');
                    $percentage = $category->budget_limit > 0 ?
                        min(100, ($currentSpending / $category->budget_limit) * 100) : 0;

                    $daysInMonth = Carbon::now()->daysInMonth;
                    $currentDay = Carbon::now()->day;
                    $expectedPercentage = ($currentDay / $daysInMonth) * 100;

                    $status = 'on_track';
                    if ($percentage > $expectedPercentage + 10) {
                        $status = 'exceeded';
                    } elseif ($percentage > $expectedPercentage + 5) {
                        $status = 'warning';
                    } elseif ($percentage < $expectedPercentage - 10) {
                        $status = 'under_spent';
                    }

                    return [
                        'category' => $category->name,
                        'limit' => $category->budget_limit,
                        'spent' => $currentSpending,
                        'percentage' => round($percentage, 1),
                        'expected_percentage' => round($expectedPercentage, 1),
                        'status' => $status,
                        'remaining' => max(0, $category->budget_limit - $currentSpending)
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $progress
            ]);
        } catch (\Exception $e) {
            \Log::error('Budget progress endpoint error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ошибка при расчете прогресса',
                'data' => []
            ]);
        }
    }

    /**
     * Финансовое здоровье
     */
    public function financialHealth(Request $request)
    {
        try {
            $userId = $this->getUserId();
            
            $validated = $request->validate([
                'year' => 'nullable|integer|min:2000|max:2100',
                'month' => 'nullable|integer|min:1|max:12'
            ]);
            
            $year = $validated['year'] ?? date('Y');
            $month = $validated['month'] ?? date('m');

            $baseData = $this->getBaseAnalytics($year, $month, 'month', $userId);
            $financialHealth = $this->calculateFinancialHealth(
                $baseData['totals']['balance'] ?? 0,
                $baseData['totals']['savings_rate'] ?? 0
            );

            return response()->json([
                'status' => 'success',
                'data' => $financialHealth
            ]);
        } catch (\Exception $e) {
            \Log::error('Financial health endpoint error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ошибка при расчете финансового здоровья',
                'data' => [
                    'score' => 0,
                    'status' => 'poor',
                    'status_label' => 'Ошибка расчета',
                    'color' => '#95a5a6'
                ]
            ]);
        }
    }

    /**
     * Детальная статистика линейной регрессии
     */
    public function regressionStats(Request $request)
    {
        try {
            $forecasts = $this->calculateForecasts();

            $detailedStats = [
                'income_regression' => [
                    'equation' => "y = " . number_format($forecasts['income_regression']['a'], 2) . " + " .
                        number_format($forecasts['income_regression']['b'], 2) . "·x",
                    'a_interpretation' => "Базовый уровень доходов: " . number_format($forecasts['income_regression']['a'], 0) . " Br",
                    'b_interpretation' => $forecasts['income_regression']['b'] > 100 ?
                        "Тренд роста: +" . number_format($forecasts['income_regression']['b'], 0) . " Br/мес" :
                        ($forecasts['income_regression']['b'] < -100 ?
                            "Тренд снижения: " . number_format($forecasts['income_regression']['b'], 0) . " Br/мес" :
                            "Стабильный тренд"),
                    'r_squared_interpretation' => $forecasts['income_regression']['r_squared'] >= 0.8 ?
                        "Высокая точность прогноза (R² = " . number_format($forecasts['income_regression']['r_squared'], 3) . ")" :
                        ($forecasts['income_regression']['r_squared'] >= 0.5 ?
                            "Средняя точность прогноза (R² = " . number_format($forecasts['income_regression']['r_squared'], 3) . ")" :
                            "Низкая точность прогноза (R² = " . number_format($forecasts['income_regression']['r_squared'], 3) . ")"),
                    'next_month_forecast' => $forecasts['next_month_income']
                ],
                'expense_regression' => [
                    'equation' => "y = " . number_format($forecasts['expense_regression']['a'], 2) . " + " .
                        number_format($forecasts['expense_regression']['b'], 2) . "·x",
                    'a_interpretation' => "Базовый уровень расходов: " . number_format($forecasts['expense_regression']['a'], 0) . " Br",
                    'b_interpretation' => $forecasts['expense_regression']['b'] > 100 ?
                        "Тренд роста: +" . number_format($forecasts['expense_regression']['b'], 0) . " Br/мес" :
                        ($forecasts['expense_regression']['b'] < -100 ?
                            "Тренд снижения: " . number_format($forecasts['expense_regression']['b'], 0) . " Br/мес" :
                            "Стабильный тренд"),
                    'r_squared_interpretation' => $forecasts['expense_regression']['r_squared'] >= 0.8 ?
                        "Высокая точность прогноза (R² = " . number_format($forecasts['expense_regression']['r_squared'], 3) . ")" :
                        ($forecasts['expense_regression']['r_squared'] >= 0.5 ?
                            "Средняя точность прогноза (R² = " . number_format($forecasts['expense_regression']['r_squared'], 3) . ")" :
                            "Низкая точность прогноза (R² = " . number_format($forecasts['expense_regression']['r_squared'], 3) . ")"),
                    'next_month_forecast' => $forecasts['next_month_expense']
                ]
            ];

            return response()->json([
                'status' => 'success',
                'data' => $detailedStats
            ]);
        } catch (\Exception $e) {
            \Log::error('Regression stats endpoint error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Ошибка при расчете статистики регрессии',
                'data' => []
            ]);
        }
    }
}