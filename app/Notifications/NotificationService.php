<?php

namespace App\Notifications;

use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Raises operational notifications.
 *
 * Called from inside the business transaction that caused them, so an action that rolls back tells
 * nobody it happened. A notification that cannot be written is reported and swallowed: being unable
 * to announce a material issue must never stop the material issue.
 */
class NotificationService
{
    public function __construct(private AudienceResolver $audience) {}

    /**
     * Tell the people this type is addressed to.
     *
     * @param  array{
     *     title: string,
     *     body?: ?string,
     *     url?: ?string,
     *     subject?: ?Model,
     *     department_id?: ?int,
     *     customer_id?: ?int,
     *     users?: iterable<User|int>,
     *     actor?: ?User,
     *     dedupe_key?: ?string,
     * }  $payload
     */
    public function raise(NotificationType $type, array $payload): ?Notification
    {
        try {
            return $this->write($type, $payload);
        } catch (UniqueConstraintViolationException) {
            return null; // Another scan raised the same occurrence first.
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function write(NotificationType $type, array $payload): ?Notification
    {
        $dedupeKey = $payload['dedupe_key'] ?? null;

        if ($dedupeKey && Notification::where('dedupe_key', $dedupeKey)->exists()) {
            return null;
        }

        $subject = $payload['subject'] ?? null;
        $actor = $payload['actor'] ?? null;
        $departmentId = $payload['department_id'] ?? null;

        $recipientIds = array_values(array_diff(
            $this->audience->resolve($this->enabledAudiences($type, $payload['audiences'] ?? null), [
                'users' => $payload['users'] ?? [],
                'department_id' => $departmentId,
                'customer_id' => $payload['customer_id'] ?? null,
            ]),
            // Nobody is told about their own action.
            array_filter([$actor?->id]),
        ));

        if ($recipientIds === []) {
            return null;
        }

        return DB::transaction(function () use ($type, $payload, $subject, $actor, $departmentId, $dedupeKey, $recipientIds) {
            $notification = Notification::create([
                'type' => $type,
                'source' => $type->source(),
                'severity' => $type->severity(),
                'title_ar' => $payload['title'],
                'body_ar' => $payload['body'] ?? null,
                'url' => $payload['url'] ?? null,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'department_id' => $departmentId,
                'triggered_by_user_id' => $actor?->id,
                'dedupe_key' => $dedupeKey,
            ]);

            $now = now();
            NotificationRecipient::insert(array_map(fn (int $userId) => [
                'notification_id' => $notification->id,
                'user_id' => $userId,
                'created_at' => $now,
            ], $recipientIds));

            return $notification;
        });
    }

    /**
     * The audiences to deliver to, less the ones switched off in the settings matrix.
     *
     * The caller may widen or narrow the catalogue's default, which is how an overdue stage
     * escalates from its department to production management and then to the administrator.
     *
     * @param  ?list<string>  $override
     * @return list<string>
     */
    private function enabledAudiences(NotificationType $type, ?array $override = null): array
    {
        $disabled = NotificationSetting::disabledMap();

        return array_values(array_filter(
            $override ?? $type->audiences(),
            fn (string $audience) => ! isset($disabled[$type->value.'|'.$audience]),
        ));
    }

    /**
     * A message written by a manager to a department, to named employees, or to everyone.
     *
     * @param  array{users?: iterable<User|int>, department_id?: ?int, everyone?: bool}  $target
     */
    public function announce(string $title, ?string $body, array $target, User $actor): ?Notification
    {
        $users = $target['users'] ?? [];
        $departmentId = $target['department_id'] ?? null;

        if (! empty($target['everyone'])) {
            $users = $this->audience->resolve(['permission:production.view', 'permission:orders.view', 'permission:inventory.view', 'permission:delivery.view', 'permission:purchasing.view', 'permission:receivables.view']);
            $departmentId = null;
        }

        return $this->raise(NotificationType::Announcement, [
            'title' => $title,
            'body' => $body,
            'users' => $users,
            'department_id' => $departmentId,
            'actor' => $actor,
            'url' => route('notifications.index'),
        ]);
    }

    public function unreadCount(User $user): int
    {
        return NotificationRecipient::forUser($user)->unread()->count();
    }

    /**
     * Mark one of the user's notifications read. Returns the notification's link, if it has one.
     */
    public function markRead(NotificationRecipient $recipient, User $user): ?string
    {
        abort_unless($recipient->user_id === $user->id, 403);

        if (! $recipient->isRead()) {
            $recipient->update(['read_at' => now()]);
        }

        return $recipient->notification?->url;
    }

    public function markAllRead(User $user): int
    {
        return NotificationRecipient::forUser($user)->unread()->update(['read_at' => now()]);
    }

    /**
     * Drop what nobody needs any more, so the table stays small on shared hosting.
     *
     * @return array{read: int, unread: int}
     */
    public function prune(int $readDays = 60, int $unreadDays = 180): array
    {
        $deleted = ['read' => 0, 'unread' => 0];

        $deleted['read'] = NotificationRecipient::whereNotNull('read_at')
            ->where('read_at', '<', now()->subDays($readDays))->delete();
        $deleted['unread'] = NotificationRecipient::whereNull('read_at')
            ->where('created_at', '<', now()->subDays($unreadDays))->delete();

        Notification::whereDoesntHave('recipients')->delete();

        return $deleted;
    }
}
