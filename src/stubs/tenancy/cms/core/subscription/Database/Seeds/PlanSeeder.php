<?php

namespace cms\core\subscription\Database\Seeds;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // ── Insert plans ───────────────────────────────────
        DB::table('plans')->insert([
            [
                'name'          => 'Basic',
                'slug'          => 'basic',
                'description'   => 'Perfect for small teams',
                'price'         => 9.99,
                'billing_cycle' => 'monthly',
                'max_users'     => 5,
                'max_modules'   => 3,
                'is_active'     => true,
                'order'         => 1,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
            [
                'name'          => 'Pro',
                'slug'          => 'pro',
                'description'   => 'For growing businesses',
                'price'         => 29.99,
                'billing_cycle' => 'monthly',
                'max_users'     => 25,
                'max_modules'   => -1,
                'is_active'     => true,
                'order'         => 2,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
            [
                'name'          => 'Enterprise',
                'slug'          => 'enterprise',
                'description'   => 'Unlimited everything',
                'price'         => 99.99,
                'billing_cycle' => 'monthly',
                'max_users'     => -1,
                'max_modules'   => -1,
                'is_active'     => true,
                'order'         => 3,
                'created_at'    => now(),
                'updated_at'    => now(),
            ],
        ]);

        $basicId      = DB::table('plans')->where('slug', 'basic')->value('id');
        $proId        = DB::table('plans')->where('slug', 'pro')->value('id');
        $enterpriseId = DB::table('plans')->where('slug', 'enterprise')->value('id');

        // ── Read modules from modules table ───────────────
        $allModules  = DB::table('modules')->get();
        $coreModules = DB::table('modules')->where('type', 1)->get(); // type=1 core
        $localModules = DB::table('modules')->where('type', 2)->get(); // type=2 local

        if ($allModules->isEmpty()) {
            $this->command->warn('⚠️  Modules table is empty! Run update:cms-module first.');
            return;
        }

        $features = [];

        foreach ($allModules as $module) {

            // Skip subscription module — that's central only
            if ($module->name === 'subscription') continue;

            $features[] = [
                'plan_id'       => $basicId,
                'feature_key'   => 'module_' . $module->name,
                // Basic gets core modules only
                'feature_value' => ($module->type == 1) ? 'true' : 'false',
                'created_at'    => now(),
                'updated_at'    => now(),
            ];

            $features[] = [
                'plan_id'       => $proId,
                'feature_key'   => 'module_' . $module->name,
                // Pro gets all modules
                'feature_value' => 'true',
                'created_at'    => now(),
                'updated_at'    => now(),
            ];
        }

        // Enterprise — wildcard (all modules)
        $features[] = [
            'plan_id'       => $enterpriseId,
            'feature_key'   => 'module_all',
            'feature_value' => 'true',
            'created_at'    => now(),
            'updated_at'    => now(),
        ];

        DB::table('plan_features')->insert($features);

        // ── Show what was seeded ───────────────────────────
        $this->command->info('✅ Plans seeded!');
        $this->command->table(
            ['Plan', 'Modules Access'],
            [
                ['Basic',      'Core modules only (' . $coreModules->count() . ' modules)'],
                ['Pro',        'All modules (' . $allModules->count() . ' modules)'],
                ['Enterprise', 'Unlimited (wildcard)'],
            ]
        );
    }
}
