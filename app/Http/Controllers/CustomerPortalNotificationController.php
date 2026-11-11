<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Notifications\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The customer's own notifications. A customer account is never an audience of factory
 * notifications, so this only ever shows what was addressed to their portal.
 */
class CustomerPortalNotificationController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->isCustomerAccount(), 403);

        $notifications = Notification::query()
            ->whereIn('id', NotificationRecipient::forUser($user)->select('notification_id'))
            ->latest('id')
            ->paginate(20);

        return view('portal.notifications', [
            'notifications' => $notifications,
            'readState' => NotificationRecipient::forUser($user)
                ->whereIn('notification_id', $notifications->pluck('id'))->pluck('read_at', 'notification_id'),
            'recipientIds' => NotificationRecipient::forUser($user)
                ->whereIn('notification_id', $notifications->pluck('id'))->pluck('id', 'notification_id'),
            'unreadCount' => $this->notifications->unreadCount($user),
        ]);
    }

    public function read(Request $request, NotificationRecipient $recipient): RedirectResponse
    {
        abort_unless($request->user()->isCustomerAccount(), 403);

        $url = $this->notifications->markRead($recipient, $request->user());

        return redirect($url ?: route('portal.notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isCustomerAccount(), 403);

        $this->notifications->markAllRead($request->user());

        return back()->with('success', 'تم تعليم كل الإشعارات كمقروءة.');
    }

    public function unreadCount(Request $request): JsonResponse
    {
        abort_unless($request->user()->isCustomerAccount(), 403);

        return response()->json(['count' => $this->notifications->unreadCount($request->user())]);
    }
}
