<?php

namespace App\Services\Analytics;

use App\Models\Transaction;
use App\Models\Category;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FinancialHealthService
{
    public function calculate(int $userId): array
    {
        try {
            $currentDate = Carbon::now();
            $salaryDay = $this->getUserSalaryDay($userId);
            $threeMonthsAgo = $currentDate->copy()->subMonths(3);

            // Средние доходы/расходы за 3 месяца
            $stats = Transaction::where('user_id', $userId)
                ->whereBetween('date', [$threeMonthsAgo, $currentDate])
                ->selectRaw('
                    SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as total_income,
                    SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as total_expense
                ')
                ->first();

            $avgMonthlyIncome = ($stats->total_income ?? 0) / 3;
            $avgMonthlyExpense = ($stats->total_expense ?? 0) / 3;

            // Общие сбережения
            $allStats = Transaction::where('user_id', $userId)
                ->selectRaw('
                    SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as total_income_all,
                    SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as total_expense_all
                ')
                ->first();

            $savings = max(0, ($allStats->total_income_all ?? 0) - ($allStats->total_expense_all ?? 0));

            // Долговая нагрузка
            $monthlyLoanPayments = $this->getMonthlyLoanPayments($userId);

            // Текущий цикл
            $cycleBalance = $this->getCurrentCycleBalance($userId, $currentDate, $salaryDay);
            $daysUntilSalary = $this->getDaysUntilNextSalary($userId, $salaryDay, $currentDate);
            $neededUntilSalary = ($avgMonthlyExpense / 30) * max(1, $daysUntilSalary);

            // Расчет компонентов
            $liquidityScore = $this->calculateLiquidityScore($cycleBalance, $neededUntilSalary);
            $emergencyFundScore = $this->calculateEmergencyFundScore($savings, $avgMonthlyExpense);
            $debtLoadScore = $this->calculateDebtLoadScore($avgMonthlyIncome, $monthlyLoanPayments);
            $savingsRateScore = $this->calculateSavingsRateScore($avgMonthlyIncome, $avgMonthlyExpense, $monthlyLoanPayments);

            $totalScore = round(
                ($liquidityScore * 0.30) +
                ($emergencyFundScore * 0.30) +
                ($debtLoadScore * 0.20) +
                ($savingsRateScore * 0.20)
            );
            $totalScore = min(100, max(0, $totalScore));

            $status = $this->getStatusInfo($totalScore);

            return [
                'score' => $totalScore,
                'status' => $status['status'],
                'status_label' => $status['label'],
                'color' => $status['color'],
                'components' => [
                    'liquidity' => [
                        'score' => $liquidityScore,
                        'balance' => round($cycleBalance, 2),
                        'needed_until_salary' => round($neededUntilSalary, 2),
                        'days_until_salary' => $daysUntilSalary
                    ],
                    'emergency_fund' => [
                        'score' => $emergencyFundScore,
                        'savings' => round($savings, 2),
                        'months_coverage' => $avgMonthlyExpense > 0 ? round($savings / $avgMonthlyExpense, 1) : 0
                    ],
                    'debt_load' => [
                        'score' => $debtLoadScore,
                        'monthly_payments' => round($monthlyLoanPayments, 2),
                        'percent_of_income' => $avgMonthlyIncome > 0 ? round(($monthlyLoanPayments / $avgMonthlyIncome) * 100) : 0
                    ],
                    'savings_rate' => [
                        'score' => $savingsRateScore,
                        'rate' => $avgMonthlyIncome > 0 ? round((($avgMonthlyIncome - $avgMonthlyExpense - $monthlyLoanPayments) / $avgMonthlyIncome) * 100, 1) : 0,
                        'saved_amount' => round($avgMonthlyIncome - $avgMonthlyExpense - $monthlyLoanPayments, 2)
                    ]
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Financial health error: ' . $e->getMessage());
            return $this->getDefaultHealth();
        }
    }

    private function getUserSalaryDay(int $userId): int
    {
        $user = User::find($userId);
        return $user->salary_day ?? 25;
    }

    private function getDaysUntilNextSalary(int $userId, int $salaryDay, Carbon $currentDate): int
    {
        $today = $currentDate->copy()->startOfDay();
        $nextSalaryDate = Carbon::create($today->year, $today->month, $salaryDay)->startOfDay();

        if ($nextSalaryDate <= $today) {
            $nextSalaryDate->addMonth();
        }

        return $today->diffInDays($nextSalaryDate);
    }

    private function getMonthlyLoanPayments(int $userId): float
    {
        $loanCategory = Category::where('user_id', $userId)
            ->whereIn('name', ['Кредиты', 'Кредит', 'Займы', 'Рассрочка'])
            ->where('type', 'expense')
            ->first();

        if (!$loanCategory) return 0;

        $threeMonthsAgo = Carbon::now()->subMonths(3);

        return Transaction::where('user_id', $userId)
                ->whereHas('categories', fn($q) => $q->where('categories.id', $loanCategory->id))
                ->whereBetween('date', [$threeMonthsAgo, Carbon::now()])
                ->sum('amount') / 3;
    }

    private function getCurrentCycleBalance(int $userId, Carbon $currentDate, int $salaryDay): float
    {
        $lastSalaryDate = Carbon::create($currentDate->year, $currentDate->month, $salaryDay);
        if ($lastSalaryDate > $currentDate) $lastSalaryDate->subMonth();

        $income = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->whereBetween('date', [$lastSalaryDate, $currentDate])
            ->sum('amount');

        $expense = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereBetween('date', [$lastSalaryDate, $currentDate])
            ->sum('amount');

        return $income - $expense;
    }

    private function calculateLiquidityScore(float $balance, float $needed): int
    {
        if ($balance <= 0) return 0;
        if ($balance >= $needed * 1.5) return 100;
        if ($balance >= $needed) return 70;
        if ($balance >= $needed * 0.7) return 40;
        return 10;
    }

    private function calculateEmergencyFundScore(float $savings, float $monthlyExpense): int
    {
        if ($monthlyExpense <= 0) return 50;
        if ($savings >= $monthlyExpense * 3) return 100;
        if ($savings >= $monthlyExpense * 2) return 70;
        if ($savings >= $monthlyExpense * 1) return 40;
        if ($savings >= $monthlyExpense * 0.5) return 20;
        return 0;
    }

    private function calculateDebtLoadScore(float $income, float $loanPayments): int
    {
        if ($income <= 0) return 0;
        $ratio = $loanPayments / $income;
        if ($ratio <= 0.2) return 100;
        if ($ratio <= 0.35) return 60;
        if ($ratio <= 0.5) return 30;
        return 0;
    }

    private function calculateSavingsRateScore(float $income, float $expense, float $loanPayments): int
    {
        $rate = $income > 0 ? (($income - $expense - $loanPayments) / $income) * 100 : 0;
        if ($rate >= 20) return 100;
        if ($rate >= 10) return 70;
        if ($rate >= 5) return 40;
        if ($rate > 0) return 20;
        return 0;
    }

    private function getStatusInfo(int $score): array
    {
        if ($score >= 80) return ['status' => 'excellent', 'label' => 'Отлично', 'color' => '#27ae60'];
        if ($score >= 60) return ['status' => 'good', 'label' => 'Хорошо', 'color' => '#2ecc71'];
        if ($score >= 40) return ['status' => 'fair', 'label' => 'Удовлетворительно', 'color' => '#f39c12'];
        if ($score >= 20) return ['status' => 'poor', 'label' => 'Плохо', 'color' => '#e74c3c'];
        return ['status' => 'critical', 'label' => 'Критично', 'color' => '#c0392b'];
    }

    private function getDefaultHealth(): array
    {
        return [
            'score' => 0,
            'status' => 'poor',
            'status_label' => 'Не определено',
            'color' => '#95a5a6',
            'components' => []
        ];
    }
}