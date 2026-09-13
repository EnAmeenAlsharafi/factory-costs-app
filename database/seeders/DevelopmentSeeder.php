<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->call(CustomerSeeder::class);

        $roles = Role::whereIn('name', [
            'admin',
            'production_manager',
            'customer_service',
            'warehouse_keeper',
            'production_worker',
            'delivery_user',
        ])->get()->keyBy('name');

        $warehouseDepartmentId = Department::where('code', 'WAREHOUSE')->value('id');
        $carpentryDepartmentId = Department::where('code', 'CARPENTRY')->value('id');

        $users = [
            ['username' => 'admin', 'name' => 'مدير النظام', 'email' => 'admin@sadir-factory.com', 'password' => 'admin123', 'role' => 'admin', 'department_id' => null, 'is_active' => true],
            ['username' => 'prod.manager', 'name' => 'م. خالد - مدير الإنتاج', 'email' => 'production@sadir-factory.com', 'password' => 'password', 'role' => 'production_manager', 'department_id' => null, 'is_active' => true],
            ['username' => 'cs.agent', 'name' => 'فاطمة - خدمة العملاء', 'email' => 'cs@sadir-factory.com', 'password' => 'password', 'role' => 'customer_service', 'department_id' => null, 'is_active' => true],
            ['username' => 'warehouse.keeper', 'name' => 'سعد - أمين المستودع', 'email' => 'warehouse@sadir-factory.com', 'password' => 'password', 'role' => 'warehouse_keeper', 'department_id' => $warehouseDepartmentId, 'is_active' => true],
            ['username' => 'worker.carpenter', 'name' => 'عمر - نجار الإنتاج', 'email' => 'worker.carpenter@sadir-factory.com', 'password' => 'password', 'role' => 'production_worker', 'department_id' => $carpentryDepartmentId, 'is_active' => true],
            ['username' => 'delivery.driver', 'name' => 'سامي - سائق التوصيل', 'email' => 'delivery@sadir-factory.com', 'password' => 'password', 'role' => 'delivery_user', 'department_id' => null, 'is_active' => true],
            ['username' => 'inactive.user', 'name' => 'موظف سابق (معطل)', 'email' => 'inactive@sadir-factory.com', 'password' => 'password', 'role' => 'production_worker', 'department_id' => $carpentryDepartmentId, 'is_active' => false],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['username' => $userData['username']],
                [
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => Hash::make($userData['password']),
                    'role_id' => $roles->get($userData['role'])?->id,
                    'department_id' => $userData['department_id'],
                    'is_active' => $userData['is_active'],
                ],
            );
        }
    }
}
