<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class BudgetMethodController extends Controller
{
    private function getUserId()
    {
        $userId = Auth::id();
        if (!$userId) abort(401, 'Unauthorized');
        return $userId;
    }

    /**
     * Правило 50/30/20
     */
    public function rule503020(Request $request)
    {
        try {
            $userId = $this->getUserId();
            $validated = $request->validate([
                'month' => 'nullable|integer|min:1|max:12',
                'year' => 'nullable|integer|min:2000|max:2100'
            ]);

            $month = $validated['month'] ?? date('m');
            $year = $validated['year'] ?? date('Y');
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            $income = Transaction::where('user_id', $userId)
                ->where('type', 'income')
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount');

            if ($income == 0) {
                return response()->json(['status' => 'error', 'message' => 'Нет доходов за выбранный период'], 422);
            }

            $categories = Category::where('user_id', $userId)->where('type', 'expense')->get();

            $needsKeywords = ['продукт', 'еда', 'супермаркет', 'магазин', 'коммунальн', 'квартплат',
                'транспорт', 'бензин', 'такси', 'проездн', 'медицин', 'аптек', 'лекарств',
                'образован', 'связь', 'интернет', 'страхован'];

            $wantsKeywords = ['развлечени', 'кафе', 'ресторан', 'хобби', 'подарк', 'шопинг',
                'одежд', 'обув', 'косметик', 'кино', 'концерт', 'спортзал', 'фитнес'];

            $needs = $wants = 0;

            foreach ($categories as $category) {
                $total = Transaction::where('user_id', $userId)
                    ->where('category_id', $category->id)
                    ->where('type', 'expense')
                    ->whereBetween('date', [$startDate, $endDate])
                    ->sum('amount');

                if ($total == 0) continue;

                $catName = mb_strtolower($category->name);
                $isNeed = false;
                foreach ($needsKeywords as $keyword) {
                    if (str_contains($catName, $keyword)) { $isNeed = true; break; }
                }
                $isWant = false;
                foreach ($wantsKeywords as $keyword) {
                    if (str_contains($catName, $keyword)) { $isWant = true; break; }
                }

                if ($isNeed) $needs += $total;
                elseif ($isWant) $wants += $total;
            }

            $savings = $income - $needs - $wants;
            $needsTarget = $income * 0.5;
            $wantsTarget = $income * 0.3;
            $savingsTarget = $income * 0.2;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'period' => ['month' => $month, 'year' => $year, 'label' => $startDate->translatedFormat('F Y')],
                    'income' => round($income, 2),
                    'breakdown' => [
                        'needs' => ['actual' => round($needs, 2), 'target' => round($needsTarget, 2), 'percentage' => round(($needs / $income) * 100, 1)],
                        'wants' => ['actual' => round($wants, 2), 'target' => round($wantsTarget, 2), 'percentage' => round(($wants / $income) * 100, 1)],
                        'savings' => ['actual' => round($savings, 2), 'target' => round($savingsTarget, 2), 'percentage' => round(($savings / $income) * 100, 1)]
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Метод 60/40
     */
    public function rule6040(Request $request)
    {
        try {
            $userId = $this->getUserId();
            $validated = $request->validate([
                'month' => 'nullable|integer|min:1|max:12',
                'year' => 'nullable|integer|min:2000|max:2100'
            ]);

            $month = $validated['month'] ?? date('m');
            $year = $validated['year'] ?? date('Y');
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            $income = Transaction::where('user_id', $userId)
                ->where('type', 'income')
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount');

            if ($income == 0) {
                return response()->json(['status' => 'error', 'message' => 'Нет доходов за выбранный период'], 422);
            }

            $expenses = Transaction::where('user_id', $userId)
                ->where('type', 'expense')
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount');

            $savings = $income - $expenses;
            $targetCurrentExpenses = $income * 0.6;
            $targetSavings = $income * 0.4;

            return response()->json([
                'status' => 'success',
                'data' => [
                    'period' => ['month' => $month, 'year' => $year, 'label' => $startDate->translatedFormat('F Y')],
                    'income' => round($income, 2),
                    'expenses' => round($expenses, 2),
                    'savings' => round($savings, 2),
                    'targets' => [
                        'current_expenses' => ['target' => round($targetCurrentExpenses, 2), 'actual' => round($expenses, 2), 'percentage' => round(($expenses / $income) * 100, 1)],
                        'savings' => ['target' => round($targetSavings, 2), 'actual' => round($savings, 2), 'percentage' => round(($savings / $income) * 100, 1)]
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Метод четырёх конвертов
     */
    public function fourEnvelopes(Request $request)
    {
        try {
            $userId = $this->getUserId();
            $validated = $request->validate([
                'month' => 'nullable|integer|min:1|max:12',
                'year' => 'nullable|integer|min:2000|max:2100'
            ]);

            $month = $validated['month'] ?? date('m');
            $year = $validated['year'] ?? date('Y');
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            $income = Transaction::where('user_id', $userId)
                ->where('type', 'income')
                ->whereBetween('date', [$startDate, $endDate])
                ->sum('amount');

            if ($income == 0) {
                return response()->json(['status' => 'error', 'message' => 'Нет доходов за выбранный период'], 422);
            }

            $mandatoryKeywords = ['коммунальн', 'квартплат', 'кредит', 'связь', 'интернет', 'страхован'];
            $categories = Category::where('user_id', $userId)->where('type', 'expense')->get();

            $mandatoryTotal = 0;
            foreach ($categories as $category) {
                $catName = mb_strtolower($category->name);
                $isMandatory = false;
                foreach ($mandatoryKeywords as $keyword) {
                    if (str_contains($catName, $keyword)) { $isMandatory = true; break; }
                }
                if ($isMandatory) {
                    $mandatoryTotal += Transaction::where('user_id', $userId)
                        ->where('category_id', $category->id)
                        ->where('type', 'expense')
                        ->whereBetween('date', [$startDate, $endDate])
                        ->sum('amount');
                }
            }

            $availableForWeeks = max(0, $income - $mandatoryTotal);
            $weeklyBudget = $availableForWeeks / 4;
            $weeksData = [];

            for ($week = 1; $week <= 4; $week++) {
                $weekStart = $startDate->copy()->addWeeks($week - 1);
                $weekEnd = $weekStart->copy()->endOfWeek();
                if ($weekEnd > $endDate) $weekEnd = $endDate;

                $spent = Transaction::where('user_id', $userId)
                    ->where('type', 'expense')
                    ->whereBetween('date', [$weekStart, $weekEnd])
                    ->sum('amount');

                $weeksData[] = [
                    'week_number' => $week,
                    'start_date' => $weekStart->toDateString(),
                    'end_date' => $weekEnd->toDateString(),
                    'budget' => round($weeklyBudget, 2),
                    'spent' => round($spent, 2),
                    'remaining' => round($weeklyBudget - $spent, 2)
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'period' => ['month' => $month, 'year' => $year, 'label' => $startDate->translatedFormat('F Y')],
                    'income' => round($income, 2),
                    'mandatory_expenses' => round($mandatoryTotal, 2),
                    'available_for_weeks' => round($availableForWeeks, 2),
                    'weekly_budget' => round($weeklyBudget, 2),
                    'weeks' => $weeksData
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }
}