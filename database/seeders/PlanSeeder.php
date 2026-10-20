<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/** Idempotent: safe to run on every deploy. Prices are placeholders until pricing is decided. */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['code' => 'free', 'name' => 'Free', 'price_monthly' => 0, 'price_yearly' => 0,
                'max_stores' => 1, 'max_branches_per_store' => 1, 'max_terminals' => 1, 'max_members' => 2, 'sort' => 1],
            ['code' => 'pro', 'name' => 'Pro', 'price_monthly' => 590, 'price_yearly' => 5900,
                'max_stores' => 3, 'max_branches_per_store' => 3, 'max_terminals' => 5, 'max_members' => 15, 'sort' => 2],
            ['code' => 'business', 'name' => 'Business', 'price_monthly' => 1990, 'price_yearly' => 19900,
                'max_stores' => null, 'max_branches_per_store' => null, 'max_terminals' => null, 'max_members' => null, 'sort' => 3],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
