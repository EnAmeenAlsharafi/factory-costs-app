<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_lots', function (Blueprint $table) {
            $table->unique('receipt_line_id', 'inventory_lots_receipt_line_unique');
        });

        Schema::table('finished_goods_movements', function (Blueprint $table) {
            $table->unique('finished_goods_receipt_id', 'fg_movements_receipt_unique');
            $table->unique('customer_return_id', 'fg_movements_return_unique');
            $table->unique(['delivery_order_line_id', 'movement_type'], 'fg_movements_delivery_type_unique');
        });

        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $this->replaceForeignKey('customer_order_lines', 'customer_order_id', 'customer_orders', 'restrict');
        $this->replaceForeignKey('customer_order_changes', 'customer_order_id', 'customer_orders', 'restrict');
        $this->replaceForeignKey('production_orders', 'customer_order_id', 'customer_orders', 'restrict');
        $this->replaceForeignKey('production_orders', 'customer_order_line_id', 'customer_order_lines', 'restrict');
        $this->replaceForeignKey('production_order_operations', 'production_order_id', 'production_orders', 'restrict');
        $this->replaceForeignKey('material_receipt_lines', 'material_receipt_id', 'material_receipts', 'restrict');
        $this->replaceForeignKey('finished_goods_receipts', 'production_order_id', 'production_orders', 'restrict');
        $this->replaceForeignKey('finished_goods_receipts', 'customer_order_id', 'customer_orders', 'restrict');
        $this->replaceForeignKey('finished_goods_movements', 'production_order_id', 'production_orders', 'restrict');
        $this->replaceForeignKey('finished_goods_movements', 'customer_order_id', 'customer_orders', 'restrict');
        $this->replaceForeignKey('delivery_orders', 'customer_order_id', 'customer_orders', 'restrict');
        $this->replaceForeignKey('delivery_order_lines', 'delivery_order_id', 'delivery_orders', 'restrict');
        $this->replaceForeignKey('delivery_events', 'delivery_order_id', 'delivery_orders', 'restrict');
        $this->replaceForeignKey('customer_returns', 'customer_order_id', 'customer_orders', 'restrict');
        $this->replaceForeignKey('purchase_order_lines', 'purchase_order_id', 'purchase_orders', 'restrict');
        $this->replaceForeignKey('customer_payment_allocations', 'customer_payment_id', 'customer_payments', 'restrict');
        $this->replaceForeignKey('customer_payment_allocations', 'customer_order_id', 'customer_orders', 'restrict');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('finished_goods_movements', function (Blueprint $table) {
                $table->dropUnique('fg_movements_receipt_unique');
                $table->dropUnique('fg_movements_return_unique');
                $table->dropUnique('fg_movements_delivery_type_unique');
            });

            Schema::table('inventory_lots', function (Blueprint $table) {
                $table->dropUnique('inventory_lots_receipt_line_unique');
            });

            return;
        }

        $this->replaceForeignKey('customer_order_lines', 'customer_order_id', 'customer_orders', 'cascade');
        $this->replaceForeignKey('customer_order_changes', 'customer_order_id', 'customer_orders', 'cascade');
        $this->replaceForeignKey('production_orders', 'customer_order_id', 'customer_orders', 'cascade');
        $this->replaceForeignKey('production_orders', 'customer_order_line_id', 'customer_order_lines', 'cascade');
        $this->replaceForeignKey('production_order_operations', 'production_order_id', 'production_orders', 'cascade');
        $this->replaceForeignKey('material_receipt_lines', 'material_receipt_id', 'material_receipts', 'cascade');
        $this->replaceForeignKey('finished_goods_receipts', 'production_order_id', 'production_orders', 'cascade');
        $this->replaceForeignKey('finished_goods_receipts', 'customer_order_id', 'customer_orders', 'cascade');
        $this->replaceForeignKey('finished_goods_movements', 'production_order_id', 'production_orders', 'cascade');
        $this->replaceForeignKey('finished_goods_movements', 'customer_order_id', 'customer_orders', 'cascade');
        $this->replaceForeignKey('delivery_orders', 'customer_order_id', 'customer_orders', 'cascade');
        $this->replaceForeignKey('delivery_order_lines', 'delivery_order_id', 'delivery_orders', 'cascade');
        $this->replaceForeignKey('delivery_events', 'delivery_order_id', 'delivery_orders', 'cascade');
        $this->replaceForeignKey('customer_returns', 'customer_order_id', 'customer_orders', 'cascade');
        $this->replaceForeignKey('purchase_order_lines', 'purchase_order_id', 'purchase_orders', 'cascade');
        $this->replaceForeignKey('customer_payment_allocations', 'customer_payment_id', 'customer_payments', 'cascade');
        $this->replaceForeignKey('customer_payment_allocations', 'customer_order_id', 'customer_orders', 'cascade');

        Schema::table('finished_goods_movements', function (Blueprint $table) {
            $table->index('finished_goods_receipt_id', 'fg_movements_receipt_fk_support');
            $table->index('customer_return_id', 'fg_movements_return_fk_support');
            $table->index('delivery_order_line_id', 'fg_movements_delivery_fk_support');
        });

        Schema::table('inventory_lots', function (Blueprint $table) {
            $table->index('receipt_line_id', 'inventory_lots_receipt_line_fk_support');
        });

        Schema::table('finished_goods_movements', function (Blueprint $table) {
            $table->dropUnique('fg_movements_receipt_unique');
            $table->dropUnique('fg_movements_return_unique');
            $table->dropUnique('fg_movements_delivery_type_unique');
        });

        Schema::table('inventory_lots', function (Blueprint $table) {
            $table->dropUnique('inventory_lots_receipt_line_unique');
        });
    }

    private function replaceForeignKey(string $tableName, string $column, string $referencedTable, string $onDelete): void
    {
        Schema::table($tableName, function (Blueprint $table) use ($column, $referencedTable, $onDelete) {
            $table->dropForeign([$column]);
            $table->foreign($column)->references('id')->on($referencedTable)->onDelete($onDelete);
        });
    }
};
