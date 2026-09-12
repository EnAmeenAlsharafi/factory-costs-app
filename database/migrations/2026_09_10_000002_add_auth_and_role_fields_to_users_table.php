<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name');
            $table->string('email')->nullable()->change();
            $table->foreignId('role_id')->nullable()->after('password')->constrained('roles')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('role_id')->index();
            $table->timestamp('last_login_at')->nullable()->after('is_active');
            // Prepared nullable department_id for future operational assignment (Role vs Department separation)
            $table->unsignedBigInteger('department_id')->nullable()->after('last_login_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn(['username', 'role_id', 'is_active', 'last_login_at', 'department_id']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
