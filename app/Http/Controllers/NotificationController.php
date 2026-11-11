<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\User;
use App\Notifications\NotificationService;
use App\Notifications\NotificationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notifications) {}

    /**
     * The user's own notifications. Holders of notifications.manage may switch to everything the
     * system has raised, which is how an administrator reviews the stream without being spammed by it.
     */
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('notifications.view'), 403);

        $user = $request->user();
        $showAll = $request->query('scope') === 'all' && $user->can('notifications.manage');
        $filter = in_array($request->query('filter'), ['unread', 'all'], true) ? $request->query('filter') : 'all';
        $group = $request->query('group');

        $query = $showAll
            ? Notification::query()->with('department', 'triggeredBy')->withCount('recipients')
            : Notification::query()->with('department', 'triggeredBy')
                ->whereIn('id', NotificationRecipient::forUser($user)->select('notification_id'));

        if (! $showAll && $filter === 'unread') {
            $query->whereIn('id', NotificationRecipient::forUser($user)->unread()->select('notification_id'));
        }

        if ($group) {
            $query->whereIn('type', collect(NotificationType::cases())
                ->filter(fn (NotificationType $type) => $type->group() === $group)
                ->map(fn (NotificationType $type) => $type->value));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        $notifications = $query->latest('id')->paginate(20)->withQueryString();

        $readState = $showAll ? collect() : NotificationRecipient::forUser($user)
            ->whereIn('notification_id', $notifications->pluck('id'))
            ->pluck('read_at', 'notification_id');

        $recipientIds = $showAll ? collect() : NotificationRecipient::forUser($user)
            ->whereIn('notification_id', $notifications->pluck('id'))
            ->pluck('id', 'notification_id');

        return view('notifications.index', [
            'notifications' => $notifications,
            'readState' => $readState,
            'recipientIds' => $recipientIds,
            'unreadCount' => $this->notifications->unreadCount($user),
            'groups' => collect(NotificationType::cases())->map(fn (NotificationType $type) => $type->group())->unique()->values(),
            'departments' => Department::orderBy('name_ar')->get(),
            'filter' => $filter,
            'group' => $group,
            'showAll' => $showAll,
        ]);
    }

    /**
     * Unread count for the bell, polled by the layout.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('notifications.view'), 403);

        return response()->json(['count' => $this->notifications->unreadCount($request->user())]);
    }

    /**
     * Open what the notification points at, and mark it read on the way.
     */
    public function read(Request $request, NotificationRecipient $recipient): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.view'), 403);

        $url = $this->notifications->markRead($recipient, $request->user());

        return redirect($url ?: route('notifications.index'));
    }

    public function readAll(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.view'), 403);

        $count = $this->notifications->markAllRead($request->user());

        return back()->with('success', $count > 0 ? "تم تعليم {$count} إشعاراً كمقروء." : 'لا توجد إشعارات غير مقروءة.');
    }

    /**
     * Write an announcement to a department, to named employees, or to everyone.
     */
    public function createAnnouncement(Request $request): View
    {
        abort_unless($request->user()->can('notifications.send'), 403);

        return view('notifications.announce', [
            'departments' => Department::orderBy('name_ar')->get(),
            'employees' => User::where('is_active', true)->whereNull('customer_id')
                ->with('role', 'department')->orderBy('name')->get()
                ->reject(fn (User $user) => $user->hasRole('customer_portal')),
        ]);
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('notifications.send'), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:2000'],
            'target' => ['required', 'in:everyone,department,users'],
            'department_id' => ['required_if:target,department', 'nullable', 'exists:departments,id'],
            'user_ids' => ['required_if:target,users', 'nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ], [
            'title.required' => 'اكتب نص الإعلان.',
            'department_id.required_if' => 'اختر القسم المستهدف.',
            'user_ids.required_if' => 'اختر موظفاً واحداً على الأقل.',
        ]);

        $notification = $this->notifications->announce($data['title'], $data['body'] ?? null, [
            'everyone' => $data['target'] === 'everyone',
            'department_id' => $data['target'] === 'department' ? (int) $data['department_id'] : null,
            'users' => $data['target'] === 'users' ? $data['user_ids'] : [],
        ], $request->user());

        if (! $notification) {
            return back()->withInput()->with('error', 'لم يصل الإعلان إلى أحد: راجع الجمهور المختار.');
        }

        return redirect()->route('notifications.index')
            ->with('success', 'تم إرسال الإعلان إلى '.$notification->recipients()->count().' موظفاً.');
    }
}
