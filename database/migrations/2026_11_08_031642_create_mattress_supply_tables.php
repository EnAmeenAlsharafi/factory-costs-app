<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Supplier-made ready products (mattresses): who makes each size, the reorder level, the
     * supplier's minimum batch, the stock the supplier holds for us, supply orders linked to
     * customer order lines, and production requests sent to the supplier.
     */
    public function up(): void
    {
        Schema::create('ready_product_supply_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('reorder_level')->default(0);
            $table->unsignedInteger('production_batch_size')->default(1);
            $table->timestamps();
        });

        Schema::create('supplier_production_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('supplier_id')->constrained();
            $table->string('status')->default('REQUESTED'); // REQUESTED, PRODUCED, CANCELLED
            $table->date('request_date');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('produced_at')->nullable();
            $table->foreignId('produced_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('supplier_production_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_production_request_id')->constrained(indexName: 'sprl_request_fk')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('produced_quantity')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_id')->constrained();
            $table->foreignId('supplier_id')->constrained();
            $table->string('movement_type'); // OPENING, PRODUCED, DELIVERED, ADJUSTMENT
            $table->decimal('quantity', 12, 2); // signed: + adds to the supplier's stock, - takes from it
            $table->foreignId('supplier_production_request_id')->nullable()->constrained(indexName: 'ssm_request_fk')->nullOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('material_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['material_id', 'supplier_id']);
        });

        Schema::create('ready_product_order_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_line_id')->constrained(indexName: 'rpoa_po_line_fk')->cascadeOnDelete();
            $table->foreignId('customer_order_line_id')->constrained(indexName: 'rpoa_order_line_fk')->cascadeOnDelete();
            $table->decimal('quantity', 12, 2);
            $table->timestamps();

            $table->index('customer_order_line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ready_product_order_allocations');
        Schema::dropIfExists('supplier_stock_movements');
        Schema::dropIfExists('supplier_production_request_lines');
        Schema::dropIfExists('supplier_production_requests');
        Schema::dropIfExists('ready_product_supply_settings');
    }
};
