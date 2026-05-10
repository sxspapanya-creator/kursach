<?php

namespace App\Http\Controllers;

use App\Enum\PlanTypeEnum;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class PlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $plans = Plan::with('currency')
            ->orderBy('code')
            ->orderBy('id')
            ->get();

        if ($request->query('group_by') === 'code') {
            $grouped = $plans->groupBy('code')->map(
                fn ($items) => $items->values()
            );

            return response()->json([
                'status' => 'success',
                'group_by' => 'code',
                'data' => $grouped,
            ], 200);
        }

        return response()->json([
            'data' => $plans->values(),
            'status' => 'success',
        ], 200);
    }

    public function setPlanToUser(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $data = $request->validate([
            'plan_id' => 'required|integer|exists:plans,id',
        ]);
        $plan = Plan::findOrFail($data['plan_id']);

        $user->plan_id = $plan->id;
        $user->plan_expires_at = match ($plan->type) {
            PlanTypeEnum::MONTHLY => Carbon::now()->addMonth(),
            PlanTypeEnum::YEARLY => Carbon::now()->addYear(),
            null => null,
            default => null,
        };

        $user->save();

        return response()->json([
            'status' => 'success',
            'data' => []
        ], 200);
    }
}