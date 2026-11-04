<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 16.6 — adds ONLINE_STORE to customer_payments.payment_method so
 * paid Salla orders can be recorded with the gateway's own method name kept
 * separately (see customer_orders.external_payment_method).
 */
return new class extends Migration
{
    private const OLD_VALUES = ['CASH', 'BANK_TRANSFER', 'CARD', 'SADAD_OR_EXTERNAL', 'CHEQUE', 'OTHER'];

    private const NEW_VALUES = [...self::OLD_VALUES, 'ONLINE_STORE'];

    public function up(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->enum('payment_method', self::NEW_VALUES)->default('BANK_TRANSFER')->change();
        });
    }

    public function down(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->enum('payment_method', self::OLD_VALUES)->default('BANK_TRANSFER')->change();
        });
    }
};
