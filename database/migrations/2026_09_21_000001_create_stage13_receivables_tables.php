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
        if (! Schema::hasTable('customer_payments')) {
            Schema::create('customer_payments', function (Blueprint $table) {
                $table->id();
                $table->string('payment_number')->unique();
                $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
                $table->date('payment_date');
                $table->decimal('amount', 18, 2);
                $table->string('currency_code', 3)->default('SAR');
                $table->enum('payment_method', ['CASH', 'BANK_TRANSFER', 'CARD', 'SADAD_OR_EXTERNAL', 'CHEQUE', 'OTHER'])->default('BANK_TRANSFER');
                $table->string('reference_number')->nullable();
                $table->string('bank_reference')->nullable();
                $table->enum('status', ['DRAFT', 'PENDING_CONFIRMATION', 'CONFIRMED', 'REVERSED', 'CANCELLED'])->default('PENDING_CONFIRMATION');
                $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('confirmed_at')->nullable();
                $table->foreignId('reversed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reversed_at')->nullable();
                $table->text('reversal_reason')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('customer_payment_allocations')) {
            Schema::create('customer_payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_payment_id')->constrained('customer_payments')->cascadeOnDelete();
                $table->foreignId('customer_order_id')->constrained('customer_orders')->cascadeOnDelete();
                $table->decimal('allocated_amount', 18, 2);
                $table->enum('allocation_type', ['DEPOSIT', 'PARTIAL_PAYMENT', 'FINAL_PAYMENT', 'GENERAL_ALLOCATION'])->default('PARTIAL_PAYMENT');
                $table->text('notes')->nullable();
                $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('customer_credit_profiles')) {
            Schema::create('customer_credit_profiles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_id')->unique()->constrained('customers')->cascadeOnDelete();
                $table->boolean('credit_enabled')->default(false);
                $table->decimal('credit_limit', 18, 2)->default(0.00);
                $table->integer('credit_days')->default(30);
                $table->decimal('warning_threshold_percent', 5, 2)->default(80.00);
                $table->boolean('hold_when_exceeded')->default(true);
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payment_control_overrides')) {
            Schema::create('payment_control_overrides', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_order_id')->nullable()->constrained('customer_orders')->nullOnDelete();
                $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
                $table->enum('override_stage', ['PRODUCTION_RELEASE', 'DELIVERY_DISPATCH', 'CREDIT_LIMIT']);
                $table->decimal('requested_amount', 18, 2)->nullable();
                $table->decimal('credit_limit', 18, 2)->nullable();
                $table->decimal('current_exposure', 18, 2)->nullable();
                $table->text('reason');
                $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('customer_payment_events')) {
            Schema::create('customer_payment_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_payment_id')->constrained('customer_payments')->cascadeOnDelete();
                $table->enum('event_type', ['CREATED', 'SUBMITTED', 'CONFIRMED', 'ALLOCATED', 'REALLOCATED', 'REVERSED', 'CANCELLED']);
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->text('notes')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (Schema::hasTable('customer_orders')) {
            Schema::table('customer_orders', function (Blueprint $table) {
                if (! Schema::hasColumn('customer_orders', 'payment_terms_type')) {
                    $table->string('payment_terms_type')->default('FULL_BEFORE_PRODUCTION')->after('status');
                    $table->decimal('deposit_required_amount', 18, 2)->nullable()->after('payment_terms_type');
                    $table->decimal('deposit_required_percent', 5, 2)->nullable()->after('deposit_required_amount');
                    $table->date('payment_due_date')->nullable()->after('deposit_required_percent');
                    $table->integer('credit_days')->nullable()->after('payment_due_date');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_orders', function (Blueprint $table) {
            $table->dropColumn([
                'payment_terms_type',
                'deposit_required_amount',
                'deposit_required_percent',
                'payment_due_date',
                'credit_days',
            ]);
        });

        Schema::dropIfExists('customer_payment_events');
        Schema::dropIfExists('payment_control_overrides');
        Schema::dropIfExists('customer_credit_profiles');
        Schema::dropIfExists('customer_payment_allocations');
        Schema::dropIfExists('customer_payments');
    }
};
