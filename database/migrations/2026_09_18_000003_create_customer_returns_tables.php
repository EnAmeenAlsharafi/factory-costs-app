<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->foreignId('customer_order_id')->constrained('customer_orders')->cascadeOnDelete();
            $table->foreignId('delivery_order_id')->nullable()->constrained('delivery_orders')->nullOnDelete();
            $table->foreignId('production_order_id')->constrained('production_orders');
            $table->foreignId('customer_order_line_id')->nullable()->constrained('customer_order_lines')->nullOnDelete();
            $table->decimal('quantity', 12, 4);
            $table->string('reason_code', 40); // PRODUCT_DEFECT, TRANSPORT_DAMAGE, INSTALLATION_ISSUE, WRONG_PRODUCT, WRONG_SIZE, CUSTOMER_REQUEST, OTHER
            $table->string('condition_code', 40)->nullable(); // GOOD, NEEDS_INSPECTION, DAMAGED, SCRAP_CANDIDATE
            $table->string('status', 30)->default('REPORTED'); // REPORTED, APPROVED, RECEIVED, REJECTED, RESOLVED
            $table->timestamp('reported_at');
            $table->timestamp('received_at')->nullable();
            $table->foreignId('reported_by_user_id')->constrained('users');
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('quality_incident_id')->nullable()->constrained('quality_incidents')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('finished_goods_movements', function (Blueprint $table) {
            $table->foreign('customer_return_id', 'fgm_cus_ret_fk')->references('id')->on('customer_returns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('finished_goods_movements', function (Blueprint $table) {
            $table->dropForeign('fgm_cus_ret_fk');
        });

        Schema::dropIfExists('customer_returns');
    }
};
