<?php

namespace App\Services\Contracts;

use App\Models\User;

interface AnalyticsServiceInterface
{
    public function analyze(User $user): array;

    public function getOverview(int $userId, string $period, int $year, ?int $month): array;

    public function getForecast(int $userId): array;

    public function getMonthlyTrends(int $userId, int $months = 12): array;
}