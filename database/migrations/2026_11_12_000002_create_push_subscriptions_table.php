<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Browsers the staff app or the customer portal was installed on, so a notification can reach a
 * phone whose screen is off. One row per browser per person; a person may install it on several.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // The push service's address for this browser; it is what identifies the subscription.
            $table->string('endpoint', 500)->unique();
            $table->string('p256dh', 255);
            $table->string('auth', 255);
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'id'], 'push_sub_user_idx');
        });

        Schema::table('notification_recipients', function (Blueprint $table) {
            // Null until the scheduled sender has pushed it, so a push is never sent twice.
            $table->timestamp('pushed_at')->nullable()->after('read_at');
            $table->index(['pushed_at', 'id'], 'notif_recipient_push_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notification_recipients', function (Blueprint $table) {
            $table->dropIndex('notif_recipient_push_idx');
            $table->dropColumn('pushed_at');
        });
        Schema::dropIfExists('push_subscriptions');
    }
};
