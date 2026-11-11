<?php

namespace App\Http\Controllers;

use App\Models\NotificationRecipient;
use App\Models\PushSubscription;
use App\Notifications\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The browser's half of Web Push: registering a device, dropping it, and answering the service
 * worker when a push wakes it up.
 */
class PushSubscriptionController extends Controller
{
    public function __construct(private WebPushService $push) {}

    /**
     * What the page needs to subscribe: whether pushing is switched on, and the application's key.
     */
    public function config(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('notifications.view') || $request->user()->isCustomerAccount(), 403);

        return response()->json([
            'enabled' => $this->push->isConfigured(),
            'public_key' => $this->push->publicKey(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('notifications.view') || $request->user()->isCustomerAccount(), 403);

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        // One row per browser: re-subscribing the same browser moves it to the current user.
        PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id' => $request->user()->id,
                'p256dh' => $data['keys']['p256dh'],
                'auth' => $data['keys']['auth'],
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'last_used_at' => now(),
            ],
        );

        return response()->json(['status' => 'subscribed']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:500']]);

        PushSubscription::where('endpoint', $data['endpoint'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['status' => 'unsubscribed']);
    }

    /**
     * What the service worker should display. The push itself carries nothing, so the text never
     * passes through the push service: it is read from here, by the signed-in browser.
     */
    public function latest(Request $request): JsonResponse
    {
        $user = $request->user();

        $unread = NotificationRecipient::forUser($user)->unread()
            ->with('notification')->latest('id')->get();

        $newest = $unread->first()?->notification;

        if (! $newest) {
            return response()->json(['count' => 0]);
        }

        return response()->json([
            'count' => $unread->count(),
            'title' => $newest->title_ar,
            'body' => $unread->count() > 1
                ? $newest->body_ar."\n".'ولديك '.($unread->count() - 1).' إشعاراً آخر بانتظارك.'
                : $newest->body_ar,
            'url' => $newest->url ?: ($user->isCustomerAccount() ? route('portal.notifications.index') : route('notifications.index')),
            'tag' => 'sadir-notifications',
        ]);
    }
}
