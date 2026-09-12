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
        Schema::create('production_routings', function (Blueprint $table) {
            $table->id();
            $table->string('routing_code')->unique();
            $table->string('name_ar');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('production_routing_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_routing_id')->constrained('production_routings')->cascadeOnDelete();
            $table->foreignId('work_center_id')->constrained('work_centers')->restrictOnDelete();
            $table->string('operation_code');
            $table->string('name_ar');
            $table->integer('sequence_number')->default(1);
            $table->string('branch_key')->nullable()->comment('e.g. MAIN, BRANCH_A, BRANCH_B');
            $table->boolean('is_parallel')->default(false);
            $table->boolean('is_required')->default(true);
            $table->timestamps();
        });

        Schema::create('production_routing_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_id')->constrained('production_routing_operations')->cascadeOnDelete();
            $table->foreignId('depends_on_operation_id')->constrained('production_routing_operations')->cascadeOnDelete();
            $table->string('dependency_type')->default('FINISH_TO_START');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_routing_dependencies');
        Schema::dropIfExists('production_routing_operations');
        Schema::dropIfExists('production_routings');
    }
};
