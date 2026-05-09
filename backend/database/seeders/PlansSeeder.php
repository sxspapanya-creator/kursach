<?php

namespace Database\Seeders;

use App\Enum\PlanCodeEnum;
use App\Enum\PlanTypeEnum;
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
            'price' => 0,
            'currency_id' => Currency::first()->id,
            'code' => PlanCodeEnum::FREE,
            'type' => PlanTypeEnum::YEARLY,
        ]);

        Plan::create([
            'name' => 'Премиум',
            'description' => 'Премиум тариф',
            'price' => 15,
            'currency_id' => Currency::first()->id,
            'code' => PlanCodeEnum::PREMIUM,
            'type' => PlanTypeEnum::MONTHLY,
        ]);

        Plan::create([
            'name' => 'Премиум',
            'description' => 'Премиум тариф',
            'price' => 120,
            'currency_id' => Currency::first()->id,
            'code' => PlanCodeEnum::PREMIUM,
            'type' => PlanTypeEnum::YEARLY,
        ]);
    }
}