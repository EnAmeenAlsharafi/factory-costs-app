<?php

namespace App\Console\Commands;

use App\Models\NotificationRecipient;
use App\Models\User;
use App\Notifications\WebPushService;
use Illuminate\Console\Command;

/**
 * Wakes the phones of people who have unread notifications that have not been pushed yet.
 *
 * Runs from the scheduler every minute rather than inside the request that raised the notification:
 * a shared host has no queue worker, and nobody should wait on an HTTPS call to a push service
 * while saving a material issue.
 */
class SendPendingPushNotifications extends Command
{
    protected $signature = 'notifications:push {--minutes=60 : how far back to still consider a notification worth pushing}';

    protected $description = 'Push notifications raised since the last run to installed browsers';

    public function handle(WebPushService $push): int
    {
        if (! $push->isConfigured()) {
            $this->comment('مفاتيح VAPID غير مضبوطة — لا إرسال. ولّدها بـ notifications:vapid.');

            return self::SUCCESS;
        }

        $pending = NotificationRecipient::query()
            ->whereNull('pushed_at')
            ->whereNull('read_at')
            ->where('created_at', '>=', now()->subMinutes((int) $this->option('minutes')))
            ->get();

        // Several notifications for one person are one wake-up: the app shows what is waiting.
        $users = User::whereIn('id', $pending->pluck('user_id')->unique())->where('is_active', true)->get();
        $reached = 0;

        foreach ($users as $user) {
            $reached += $push->notify($user);
        }

        NotificationRecipient::whereIn('id', $pending->pluck('id'))->update(['pushed_at' => now()]);

        $this->info("نُبّه {$reached} متصفحاً لـ {$users->count()} موظفاً ({$pending->count()} إشعاراً).");

        return self::SUCCESS;
    }
}
