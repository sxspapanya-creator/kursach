<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\CurrencyRate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class CurrencyController extends Controller
{
    public function index(Request $request)
    {
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            $date = $request->query('date', Carbon::today()->toDateString());

            // Получаем все валюты
            $currencies = Currency::all(['id', 'code', 'name', 'symbol', 'is_base']);

            // Получаем базовую валюту BYN
            $byn = Currency::where('code', 'BYN')->first();

            // Добавляем курс для каждой валюты
            $currenciesWithRates = $currencies->map(function ($currency) use ($byn, $date) {
                if ($currency->code === 'BYN') {
                    $currency->rate = 1;
                    return $currency;
                }

                // Получаем курс на указанную дату
                $rate = CurrencyRate::where('from_currency_id', $currency->id)
                    ->where('to_currency_id', $byn->id)
                    ->where('date', $date)
                    ->first();

                // Если нет курса на эту дату, берем последний доступный
                if (!$rate) {
                    $rate = CurrencyRate::where('from_currency_id', $currency->id)
                        ->where('to_currency_id', $byn->id)
                        ->orderBy('date', 'desc')
                        ->first();
                }

                $currency->rate = $rate ? $rate->rate : null;
                return $currency;
            });

            return response()->json([
                'status' => 'success',
                'data' => $currenciesWithRates
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch currencies: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить список дат, для которых есть курсы по всем валютам
     */
    public function getAvailableDates(Request $request)
    {
        try {
            $userId = Auth::id();
            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 401);
            }

            $currencyId = $request->query('currency_id');

            if (!$currencyId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'currency_id is required'
                ], 422);
            }

            $currency = Currency::find($currencyId);

            if ($currency->code === 'BYN') {
                // Для BYN доступны все даты
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'available_dates' => [],
                        'all_dates_allowed' => true
                    ]
                ]);
            }

            $byn = Currency::where('code', 'BYN')->first();

            // Получаем все даты, для которых есть курс
            $dates = CurrencyRate::where('from_currency_id', $currencyId)
                ->where('to_currency_id', $byn->id)
                ->select('date')
                ->distinct()
                ->orderBy('date', 'asc')
                ->get()
                ->pluck('date')
                ->map(function ($date) {
                    return $date->format('Y-m-d');
                });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'available_dates' => $dates,
                    'all_dates_allowed' => false
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch available dates: ' . $e->getMessage()
            ], 500);
        }
    }
}