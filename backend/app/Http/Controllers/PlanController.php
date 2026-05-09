<?php

namespace App\Http\Controllers;

use App\Enum\PlanTypeEnum;
use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class PlanController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'data' => Plan::get(),
            'status' => 'success'
        ], 200);
    }

    public function getPlanTypes(Request $request)
    {
        return response()->json([
            'data' => PlanTypeEnum::getPlanTypes(),
            'status' => 'success'
        ], 200);
    }

    public function setPlanToUser(Request $request) {
        /** @var User $user */
        $user = Auth::user();
        $data = $request->validate([
            'plan_id' => 'required|integer|in:plans,id',
            'type' => 'required|string|in:'.implode(',', PlanTypeEnum::getPlanTypes()),
        ]);

        $user->plan_id = $data['plan_id'];
        $expiresAt = $data['type'] === PlanTypeEnum::MONTHLY
            ? Carbon::now()->addMonth()
            : Carbon::now()->addYear();
        $user->plan_expires_at = $expiresAt;

        $user->save();

        return response()->json([
            'status' => 'success',
            'data' => []
        ], 200);
    }
}