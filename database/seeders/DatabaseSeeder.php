<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Roles & Permissions
        $this->call(RoleAndPermissionSeeder::class);

        // 2. Seed Master Data Foundation
        $this->call([
            SalesChannelSeeder::class,
            CustomerTypeSeeder::class,
            DepartmentSeeder::class,
            UnitOfMeasureSeeder::class,
            MaterialCategorySeeder::class,
            WarehouseSeeder::class,
            InventoryAdjustmentReasonSeeder::class,
            StandardBedSizeSeeder::class,
            WorkCenterSeeder::class,
            DefaultBedRoutingSeeder::class,
            ProductionWasteReasonSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DevelopmentSeeder::class);
        }
    }
}
