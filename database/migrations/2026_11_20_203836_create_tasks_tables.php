<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tasks and notes («مهامي»): personal to-dos, tasks assigned to one or many employees, the
 * decline / extension requests an assignee sends back, and private notes.
 *
 * A task is written once; each person working on it has their own row in task_assignees with
 * their own status, due date and personal flags (important, my day, list), so five employees on
 * one task each finish — or ask to be excused — on their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('details')->nullable();
            $table->date('due_date')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            // True when written for other people; a personal to-do is assigned only to its author.
            $table->boolean('is_assigned')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['created_by_user_id', 'is_assigned']);
        });

        Schema::create('task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_list_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('OPEN');
            $table->date('due_date')->nullable();
            $table->boolean('is_important')->default(false);
            $table->date('my_day_on')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('task_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_assignee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->text('reason');
            $table->date('requested_due_date')->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['task_assignee_id', 'status']);
        });

        Schema::create('personal_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150)->nullable();
            $table->text('body');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_notes');
        Schema::dropIfExists('task_requests');
        Schema::dropIfExists('task_assignees');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('task_lists');
    }
};
