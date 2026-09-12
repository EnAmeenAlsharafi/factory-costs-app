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
        Schema::create('units_of_measure', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // e.g. 'METER', 'BOARD', 'PIECE'
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('symbol')->nullable(); // e.g. 'م', 'كجم'
            $table->string('unit_type')->nullable(); // e.g. 'length', 'area', 'weight', 'count', 'packaging'
            $table->boolean('allows_decimal')->default(false);
            $table->integer('decimal_precision')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_unit_id')->constrained('units_of_measure')->cascadeOnDelete();
            $table->foreignId('to_unit_id')->constrained('units_of_measure')->cascadeOnDelete();
            $table->decimal('conversion_factor', 12, 4);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['from_unit_id', 'to_unit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_conversions');
        Schema::dropIfExists('units_of_measure');
    }
};
