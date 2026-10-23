<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 16.5 — assignment audit parity between delivery orders and production
 * operations (spec §53): who assigned a delivery and when, alongside the
 * existing `assigned_user_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->timestamp('assigned_at')->nullable()->after('assigned_user_id');
            $table->foreignId('assigned_by_user_id')->nullable()->after('assigned_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_by_user_id');
            $table->dropColumn('assigned_at');
        });
    }
};
