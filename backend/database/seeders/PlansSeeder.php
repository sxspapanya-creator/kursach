<?php

namespace Database\Seeders;

use App\Enum\PlanCodeEnum;
use App\Models\Category;
use App\Models\Plan;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Currency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        Plan::create([
            'name' => 'Бесплатный',
            'description' => 'Бесплатный тариф',
            'price_monthly' => 0,
            'price_yearly' => 0,
            'currency_id' => Currency::first()->id,
            'code' => PlanCodeEnum::FREE,
        ]);

        Plan::create([
            'name' => 'Премиум',
            'description' => 'Премиум тариф',
            'price_monthly' => 15,
            'price_yearly' => 120,
            'currency_id' => Currency::first()->id,
            'code' => PlanCodeEnum::PREMIUM,
        ]);
    }
}