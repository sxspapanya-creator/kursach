<?php

namespace App\Http\Controllers;

use App\Models\Currency;

class CurrencyController extends Controller
{
    public function index()
    {
        $currencies = Currency::whereNotNull('rate')->get([
            'id', 'code', 'name', 'symbol', 'rate'
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $currencies
        ]);
    }
}