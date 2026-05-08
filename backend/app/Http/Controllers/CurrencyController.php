<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CurrencyController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->query('date', Carbon::today());
        $currencies = Currency::with(['currencyRates' => function ($query) use ($date) {
            $query->where('date', $date);
        }])->get();
        return response()->json([
            'status' => 'success',
            'data' => $currencies
        ]);
    }
}