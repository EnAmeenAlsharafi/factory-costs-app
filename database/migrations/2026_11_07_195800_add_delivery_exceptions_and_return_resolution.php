<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Structured delivery exceptions (reason, goods location, successor order, recipient)
     * and the customer-return inspection / approval / resolution trail.
     */
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->string('failure_reason_code', 40)->nullable()->after('status');
            $table->string('goods_location', 20)->nullable()->after('failure_reason_code'); // FACTORY, QUARANTINE, WITH_DRIVER
            $table->foreignId('successor_delivery_order_id')->nullable()->after('goods_location')->constrained('delivery_orders')->nullOnDelete();
            $table->string('recipient_name')->nullable()->after('delivered_at');
        });

        Schema::table('customer_returns', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('reported_at');
            $table->foreignId('approved_by_user_id')->nullable()->after('received_by_user_id')->constrained('users')->nullOnDelete();
            $table->string('inspection_decision', 20)->nullable()->after('condition_code'); // RESTOCK, REPAIR, SCRAP
            $table->timestamp('inspected_at')->nullable()->after('received_at');
            $table->foreignId('inspected_by_user_id')->nullable()->after('approved_by_user_id')->constrained('users')->nullOnDelete();
            $table->string('resolution_code', 30)->nullable()->after('inspection_decision'); // REPLACEMENT, REPAIRED_RETURNED, REFUND, CREDIT_NOTE, NO_ACTION
            $table->text('resolution_notes')->nullable()->after('notes');
            $table->timestamp('resolved_at')->nullable()->after('inspected_at');
            $table->foreignId('resolved_by_user_id')->nullable()->after('inspected_by_user_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropConstrainedForeignId('inspected_by_user_id');
            $table->dropConstrainedForeignId('resolved_by_user_id');
            $table->dropColumn(['approved_at', 'inspection_decision', 'inspected_at', 'resolution_code', 'resolution_notes', 'resolved_at']);
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('successor_delivery_order_id');
            $table->dropColumn(['failure_reason_code', 'goods_location', 'recipient_name']);
        });
    }
};
