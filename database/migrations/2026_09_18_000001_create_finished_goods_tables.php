<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finished_goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('customer_order_id')->constrained('customer_orders')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->decimal('quantity', 12, 4);
            $table->string('status', 30)->default('DRAFT'); // DRAFT, POSTED, CANCELLED
            $table->date('receipt_date');
            $table->foreignId('received_by_user_id')->constrained('users');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamp('posted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('finished_goods_movements', function (Blueprint $table) {
            $table->id();
            $table->string('movement_number')->unique();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('customer_order_id')->constrained('customer_orders')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->foreignId('finished_goods_receipt_id')->nullable()->constrained('finished_goods_receipts')->nullOnDelete();
            $table->unsignedBigInteger('delivery_order_id')->nullable();
            $table->unsignedBigInteger('delivery_order_line_id')->nullable();
            $table->unsignedBigInteger('customer_return_id')->nullable();
            $table->string('movement_type', 40); // PRODUCTION_RECEIPT, DELIVERY_DISPATCH, DELIVERY_RETURN, CUSTOMER_RETURN
            $table->string('direction', 10); // IN, OUT
            $table->decimal('quantity', 12, 4);
            $table->timestamp('occurred_at');
            $table->foreignId('performed_by_user_id')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finished_goods_movements');
        Schema::dropIfExists('finished_goods_receipts');
    }
};
