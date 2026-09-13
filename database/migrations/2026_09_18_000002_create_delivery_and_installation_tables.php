<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_orders', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_number')->unique();
            $table->foreignId('customer_order_id')->constrained('customer_orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('sales_channel_id')->nullable()->constrained('sales_channels')->nullOnDelete();
            $table->date('scheduled_delivery_date')->nullable();
            $table->string('scheduled_time_notes')->nullable();
            $table->string('status', 40)->default('DRAFT'); // DRAFT, READY_FOR_DELIVERY, ASSIGNED, OUT_FOR_DELIVERY, DELIVERED, INSTALLATION_COMPLETED, FAILED, RESCHEDULED, CANCELLED
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name_snapshot');
            $table->string('customer_phone_snapshot')->nullable();
            $table->string('city_snapshot')->nullable();
            $table->string('district_snapshot')->nullable();
            $table->text('delivery_address_snapshot')->nullable();
            $table->text('location_notes')->nullable();
            $table->boolean('installation_required')->default(true);
            $table->text('delivery_notes')->nullable();
            $table->text('installation_notes')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('dispatched_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')->constrained('delivery_orders')->cascadeOnDelete();
            $table->foreignId('customer_order_line_id')->nullable()->constrained('customer_order_lines')->nullOnDelete();
            $table->foreignId('production_order_id')->constrained('production_orders');
            $table->decimal('quantity', 12, 4);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')->constrained('delivery_orders')->cascadeOnDelete();
            $table->string('event_type', 40); // CREATED, READY, ASSIGNED, DISPATCHED, DELIVERED, INSTALLED, FAILED, RETURNED_TO_FACTORY, RESCHEDULED, NOTE
            $table->string('old_status')->nullable();
            $table->string('new_status')->nullable();
            $table->foreignId('user_id')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        // Add foreign keys to finished_goods_movements for delivery tables
        Schema::table('finished_goods_movements', function (Blueprint $table) {
            $table->foreign('delivery_order_id', 'fgm_del_ord_fk')->references('id')->on('delivery_orders')->nullOnDelete();
            $table->foreign('delivery_order_line_id', 'fgm_del_line_fk')->references('id')->on('delivery_order_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('finished_goods_movements', function (Blueprint $table) {
            $table->dropForeign('fgm_del_ord_fk');
            $table->dropForeign('fgm_del_line_fk');
        });

        Schema::dropIfExists('delivery_events');
        Schema::dropIfExists('delivery_order_lines');
        Schema::dropIfExists('delivery_orders');
    }
};
