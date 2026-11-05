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
            $table->foreignId('customer_id')->nullable()->unique()->constrained('customers')->restrictOnDelete();
        });
        Schema::create('customer_portal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_order_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('status', 30)->default('DRAFT');
            $table->unsignedInteger('revision')->default(1);
            $table->string('customer_reference')->nullable();
            $table->date('requested_delivery_date')->nullable();
            $table->date('promised_delivery_date')->nullable();
            $table->string('delivery_city')->nullable();
            $table->text('delivery_address')->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('public_response')->nullable();
            $table->text('change_note')->nullable();
            $table->json('lines');
            $table->decimal('offer_total', 12, 2)->nullable();
            $table->string('payment_terms_type', 40)->nullable();
            $table->decimal('deposit_required_amount', 12, 2)->nullable();
            $table->date('payment_due_date')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('offered_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'status']);
            $table->index(['status', 'updated_at']);
        });
        Schema::create('customer_portal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_portal_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 40);
            $table->unsignedInteger('revision');
            $table->text('message')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_portal_events');
        Schema::dropIfExists('customer_portal_requests');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropUnique(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }
};
