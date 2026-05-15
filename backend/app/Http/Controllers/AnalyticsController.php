<?php

namespace App\Http\Controllers;

use App\Services\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AnalyticsController extends Controller
{
    private AnalyticsService $analyticsService;

    public function __construct(AnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    protected function getUserId()
    {
        $userId = Auth::id();
        if (!$userId) abort(401, 'Unauthorized');
        return $userId;
    }

    public function overview(Request $request)
    {
        try {
            $validated = $request->validate([
                'period' => 'nullable|in:month,year',
                'year' => 'nullable|integer|min:2000|max:2100',
                'month' => 'nullable|integer|min:1|max:12',
            ]);

            $period = $validated['period'] ?? 'month';
            $year = $validated['year'] ?? date('Y');
            $month = $validated['month'] ?? date('m');

            $data = $this->analyticsService->getOverview($this->getUserId(), $period, $year, $month);

            return response()->json(['status' => 'success', 'data' => $data]);

        } catch (\Exception $e) {
            Log::error('Overview error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function forecast(Request $request)
    {
        try {
            $data = $this->analyticsService->getForecast($this->getUserId());
            return response()->json(['status' => 'success', 'data' => $data]);

        } catch (\Exception $e) {
            Log::error('Forecast error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function batchMarkAnomalies(Request $request)
    {
        try {
            $userId = $this->getUserId();
            $validated = $request->validate([
                'anomalies' => 'required|array',
                'anomalies.*.id' => 'required|integer|exists:transactions,id',
                'anomalies.*.is_anomaly' => 'required|boolean'
            ]);

            $anomalyService = app(\App\Services\Analytics\AnomalyService::class);
            $updatedCount = 0;
            $errors = [];

            foreach ($validated['anomalies'] as $item) {
                $success = $anomalyService->updateAnomalyStatus($item['id'], $userId, $item['is_anomaly']);
                if ($success) $updatedCount++;
                else $errors[] = $item['id'];
            }

            return response()->json([
                'status' => 'success',
                'message' => "Обновлено {$updatedCount} транзакций",
                'data' => ['updated_count' => $updatedCount, 'errors' => $errors]
            ]);

        } catch (\Exception $e) {
            Log::error('Batch mark anomalies error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    public function monthlyTrends(Request $request)
    {
        try {
            $userId = $this->getUserId();
            $months = (int) $request->input('months', 12);

            $data = $this->analyticsService->getMonthlyTrends($userId, $months);

            return response()->json(['status' => 'success', 'data' => $data]);

        } catch (\Exception $e) {
            Log::error('MonthlyTrends error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }
}