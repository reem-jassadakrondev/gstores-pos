<?php

namespace Database\Seeders;

use App\Actions\CreateOrganization;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PlanSeeder::class);

        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@admin.com',
            'password' => 'password',
        ]);
        $admin->forceFill(['is_platform_admin' => true, 'can_access_legacy' => true])->save();

        app(CreateOrganization::class)->handle($admin, [
            'name' => 'GStores',
            'business_type' => 'wholesale',
            'store_name' => 'GStores ขายส่ง',
        ]);
    }
}
