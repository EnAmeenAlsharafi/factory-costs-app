<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operational notifications. A notification points at a fact that already exists elsewhere (an order,
 * a stage, a request): it carries a text snapshot and a link, never business figures of its own.
 *
 * The message is stored once and fanned out to its recipients, so "a stage arrived in Upholstery"
 * is one notification seen by seven people, each with their own read state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64)->index();
            // SYSTEM (raised by the scheduled scan), ACTION (a user's action), MANUAL (an announcement).
            $table->string('source', 16)->default('ACTION');
            $table->string('severity', 16)->default('info');
            $table->string('title_ar');
            $table->text('body_ar')->nullable();
            $table->string('url', 500)->nullable();
            // What the notification is about, for grouping and for "everything about this order".
            $table->nullableMorphs('subject');
            $table->foreignId('department_id')->nullable()
                ->constrained('departments', indexName: 'notif_department_fk')->nullOnDelete();
            $table->foreignId('triggered_by_user_id')->nullable()
                ->constrained('users', indexName: 'notif_triggered_by_fk')->nullOnDelete();
            // One row per open occurrence: the half-hourly scan must not re-notify the same late stage.
            $table->string('dedupe_key', 191)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['notification_id', 'user_id'], 'notif_recipient_unique');
            // The bell: unread count and the user's own list.
            $table->index(['user_id', 'read_at', 'id'], 'notif_recipient_user_idx');
        });

        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64);
            // The audience as the catalogue declares it, e.g. "permission:production.view" or "department:stage".
            $table->string('audience_key', 128);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->unique(['type', 'audience_key'], 'notif_setting_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
        Schema::dropIfExists('notification_recipients');
        Schema::dropIfExists('notifications');
    }
};
