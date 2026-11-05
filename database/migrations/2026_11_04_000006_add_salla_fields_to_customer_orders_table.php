<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 16.6 — delivery snapshot and Salla order reference fields on
 * customer_orders, plus the import batch link and a uniqueness guard on
 * (sales_channel_id, external_order_reference) so the same Salla order can
 * never be imported twice. Regular (not partial) unique index: both MariaDB
 * and SQLite treat NULL as distinct, so rows without a reference are
 * unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Resolve existing duplicate (sales_channel_id, external_order_reference) pairs if any
        $duplicates = DB::table('customer_orders')
            ->select('sales_channel_id', 'external_order_reference')
            ->whereNotNull('external_order_reference')
            ->whereNotNull('sales_channel_id')
            ->groupBy('sales_channel_id', 'external_order_reference')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            $orders = DB::table('customer_orders')
                ->where('sales_channel_id', $dup->sales_channel_id)
                ->where('external_order_reference', $dup->external_order_reference)
                ->orderBy('id')
                ->get();

            $index = 0;
            foreach ($orders as $order) {
                if ($index > 0) {
                    DB::table('customer_orders')
                        ->where('id', $order->id)
                        ->update(['external_order_reference' => $order->external_order_reference.'-'.$order->id]);
                }
                $index++;
            }
        }

        // 2. Add columns if not already present
        if (! Schema::hasColumn('customer_orders', 'delivery_method')) {
            Schema::table('customer_orders', function (Blueprint $table) {
                $table->enum('delivery_method', ['INTERNAL_TEAM', 'COURIER', 'PICKUP'])->nullable()->after('status');
                $table->string('shipping_company_name')->nullable()->after('delivery_method');
                $table->string('shipping_tracking_number')->nullable()->after('shipping_company_name');
                $table->string('delivery_city')->nullable()->after('shipping_tracking_number');
                $table->string('delivery_district')->nullable()->after('delivery_city');
                $table->text('delivery_address')->nullable()->after('delivery_district');
                $table->string('delivery_map_url')->nullable()->after('delivery_address');
                $table->string('external_order_internal_id')->nullable()->after('external_order_reference');
                $table->string('external_payment_method')->nullable()->after('external_order_internal_id');
                $table->string('external_payment_status')->nullable()->after('external_payment_method');
                $table->foreignId('import_batch_id')->nullable()->after('external_payment_status')
                    ->constrained('sales_import_batches')->nullOnDelete();
            });
        }

        // 3. Add unique index if not present
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->unique(['sales_channel_id', 'external_order_reference'], 'customer_orders_channel_external_ref_unique');
        });
    }

    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropUnique('customer_orders_channel_external_ref_unique');
            $table->dropConstrainedForeignId('import_batch_id');
            $table->dropColumn([
                'delivery_method',
                'shipping_company_name',
                'shipping_tracking_number',
                'delivery_city',
                'delivery_district',
                'delivery_address',
                'delivery_map_url',
                'external_order_internal_id',
                'external_payment_method',
                'external_payment_status',
            ]);
        });
    }
};
