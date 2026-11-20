<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\TaskList;
use App\Models\TaskRequest;
use App\Models\User;
use App\Notifications\NotificationService;
use App\Notifications\NotificationType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tasks and notes («مهامي»).
 *
 * Who may assign to whom is decided by permissions, never by job title:
 *   tasks.assign.department — employees of the actor's own department
 *   tasks.assign.all        — any employee
 *   tasks.assign.multiple   — more than one person at once, or a whole department or role
 *
 * Each assignee has their own status and due date. What they cannot change themselves (being excused,
 * a later due date) they ask for, and the task's author approves or refuses.
 */
class TaskService
{
    public function __construct(private NotificationService $notifications) {}

    public function canAssign(User $actor): bool
    {
        return $actor->can('tasks.assign.all') || $actor->can('tasks.assign.department');
    }

    public function canAssignMultiple(User $actor): bool
    {
        return $this->canAssign($actor) && $actor->can('tasks.assign.multiple');
    }

    /**
     * Employees the actor may assign tasks to, the actor excluded.
     *
     * @return Collection<int, User>
     */
    public function assignableUsers(User $actor): Collection
    {
        if (! $this->canAssign($actor)) {
            return collect();
        }

        $staff = User::query()
            ->where('is_active', true)
            ->whereNull('customer_id')
            ->whereKeyNot($actor->id)
            ->with(['role', 'department'])
            ->orderBy('name')
            ->get()
            ->reject(fn (User $user) => $user->hasRole('customer_portal'))
            ->values();

        if ($actor->can('tasks.assign.all')) {
            return $staff;
        }

        $departmentIds = $this->reachableDepartmentIds($actor);

        return $staff->filter(fn (User $user) => in_array($user->productionDepartmentId(), $departmentIds, true))->values();
    }

    /**
     * Departments «tasks.assign.department» reaches: the actor's own, or — for production management,
     * who oversee every production department rather than belong to one — all production departments.
     *
     * @return list<int>
     */
    private function reachableDepartmentIds(User $actor): array
    {
        $departmentId = $actor->productionDepartmentId();

        if ($departmentId !== null) {
            return [$departmentId];
        }

        return $actor->hasProductionDepartmentOverride()
            ? Department::production()->pluck('id')->all()
            : [];
    }

    /**
     * Write a task. With no one to assign it to, it is the actor's own to-do.
     *
     * @param  array{title: string, details?: ?string, due_date?: ?string, task_list_id?: ?int, is_important?: bool, my_day?: bool, assignee_ids?: list<int>, department_ids?: list<int>, role_ids?: list<int>}  $data
     */
    public function create(User $actor, array $data): Task
    {
        $assigneeIds = $this->resolveAssignees($actor, $data);

        return DB::transaction(function () use ($actor, $data, $assigneeIds) {
            $task = Task::create([
                'title' => $data['title'],
                'details' => $data['details'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'created_by_user_id' => $actor->id,
                'is_assigned' => $assigneeIds !== [],
            ]);

            if ($assigneeIds === []) {
                $task->assignees()->create([
                    'user_id' => $actor->id,
                    'task_list_id' => $this->ownListId($actor, $data['task_list_id'] ?? null),
                    'due_date' => $task->due_date,
                    'is_important' => (bool) ($data['is_important'] ?? false),
                    'my_day_on' => ! empty($data['my_day']) ? today() : null,
                ]);

                return $task;
            }

            $this->addAssignees($task, $assigneeIds, $actor);

            return $task;
        });
    }

    /**
     * The author changes what the task says, its due date, or who works on it.
     *
     * A new due date reaches every assignee still working on it; those who finished keep theirs.
     * Someone removed from the task loses it, unless they already finished it.
     *
     * @param  array{title: string, details?: ?string, due_date?: ?string, assignee_ids?: list<int>, department_ids?: list<int>, role_ids?: list<int>}  $data
     */
    public function update(User $actor, Task $task, array $data): Task
    {
        $this->ensureAuthor($actor, $task);
        abort_if($task->isCancelled(), 403);

        $newAssigneeIds = $task->is_assigned ? $this->resolveAssignees($actor, $data, $task) : [];
        if ($task->is_assigned && $newAssigneeIds === []) {
            throw ValidationException::withMessages(['assignee_ids' => 'اختر موظفاً واحداً على الأقل.']);
        }

        return DB::transaction(function () use ($actor, $task, $data, $newAssigneeIds) {
            $dueDateChanged = ($task->due_date?->toDateString()) !== ($data['due_date'] ?? null);

            $task->update([
                'title' => $data['title'],
                'details' => $data['details'] ?? null,
                'due_date' => $data['due_date'] ?? null,
            ]);

            if ($dueDateChanged) {
                $task->assignees()
                    ->whereNotIn('status', [TaskAssignee::STATUS_DONE, TaskAssignee::STATUS_DECLINED])
                    ->update(['due_date' => $task->due_date]);
            }

            if (! $task->is_assigned) {
                return $task;
            }

            $currentIds = $task->assignees()->pluck('user_id')->all();
            $task->assignees()
                ->whereNotIn('user_id', $newAssigneeIds)
                ->where('status', '!=', TaskAssignee::STATUS_DONE)
                ->delete();
            $this->addAssignees($task, array_values(array_diff($newAssigneeIds, $currentIds)), $actor);

            return $task;
        });
    }

    /**
     * A personal to-do is deleted; an assigned task is cancelled, so its history stays with its author.
     */
    public function cancel(User $actor, Task $task): void
    {
        $this->ensureAuthor($actor, $task);

        if (! $task->is_assigned) {
            $task->delete();

            return;
        }

        $task->update(['cancelled_at' => now()]);
        $task->assignees()->with('pendingRequest')->get()
            ->each(fn (TaskAssignee $assignment) => $assignment->pendingRequest?->update([
                'status' => TaskRequest::STATUS_REJECTED,
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
            ]));
    }

    /**
     * The employee moves their own copy along: new → in progress → done.
     */
    public function setStatus(User $actor, TaskAssignee $assignment, string $status): void
    {
        $this->ensureOwnActive($actor, $assignment);
        abort_unless(array_key_exists($status, TaskAssignee::WORK_STATUSES), 422);

        $wasDone = $assignment->isDone();
        $assignment->update([
            'status' => $status,
            'completed_at' => $status === TaskAssignee::STATUS_DONE ? ($assignment->completed_at ?? now()) : null,
        ]);

        $task = $assignment->task;
        if ($status === TaskAssignee::STATUS_DONE && ! $wasDone && $task->is_assigned) {
            $this->notifications->raise(NotificationType::TodoCompleted, [
                'title' => "{$actor->name} أنجز مهمة: {$task->title}",
                'url' => route('tasks.show', $task),
                'subject' => $task,
                'users' => [$task->created_by_user_id],
                'actor' => $actor,
            ]);
        }
    }

    /**
     * The author sends a finished task back to the employee who finished it.
     */
    public function reopen(User $actor, TaskAssignee $assignment): void
    {
        $task = $assignment->task;
        $this->ensureAuthor($actor, $task);
        abort_unless($assignment->isDone() && ! $task->isCancelled(), 422);

        $assignment->update(['status' => TaskAssignee::STATUS_IN_PROGRESS, 'completed_at' => null]);

        $this->notifications->raise(NotificationType::TodoReopened, [
            'title' => "أُعيد فتح مهمة: {$task->title}",
            'body' => 'أعادها '.$actor->name.' إليك لاستكمالها.',
            'url' => route('tasks.show', $task),
            'subject' => $task,
            'users' => [$assignment->user_id],
            'actor' => $actor,
        ]);
    }

    public function toggleImportant(User $actor, TaskAssignee $assignment): void
    {
        $this->ensureOwn($actor, $assignment);
        $assignment->update(['is_important' => ! $assignment->is_important]);
    }

    public function toggleMyDay(User $actor, TaskAssignee $assignment): void
    {
        $this->ensureOwn($actor, $assignment);
        $assignment->update(['my_day_on' => $assignment->isInMyDay() ? null : today()]);
    }

    public function moveToList(User $actor, TaskAssignee $assignment, ?int $taskListId): void
    {
        $this->ensureOwn($actor, $assignment);
        $assignment->update(['task_list_id' => $this->ownListId($actor, $taskListId)]);
    }

    /**
     * The employee asks to be excused from an assigned task, or for a later due date.
     */
    public function submitRequest(User $actor, TaskAssignee $assignment, string $type, string $reason, ?string $requestedDueDate = null): TaskRequest
    {
        $this->ensureOwnActive($actor, $assignment);
        $task = $assignment->task;
        abort_unless($task->is_assigned && ! $task->isCreatedBy($actor), 403);

        if ($assignment->pendingRequest()->exists()) {
            throw ValidationException::withMessages(['reason' => 'لديك طلب سابق على هذه المهمة لم يُرد عليه بعد.']);
        }

        $request = $assignment->requests()->create([
            'type' => $type,
            'reason' => $reason,
            'requested_due_date' => $type === TaskRequest::TYPE_EXTEND ? $requestedDueDate : null,
            'status' => TaskRequest::STATUS_PENDING,
        ]);

        $what = $type === TaskRequest::TYPE_DECLINE
            ? 'يطلب الاعتذار عن'
            : 'يطلب تمديد موعد';

        $this->notifications->raise(NotificationType::TodoRequestSubmitted, [
            'title' => "{$actor->name} {$what} مهمة: {$task->title}",
            'body' => $reason,
            'url' => route('tasks.show', $task),
            'subject' => $task,
            'users' => [$task->created_by_user_id],
            'actor' => $actor,
        ]);

        return $request;
    }

    /**
     * The author approves or refuses an assignee's request.
     */
    public function decideRequest(User $actor, TaskRequest $request, bool $approve): void
    {
        $assignment = $request->assignment;
        $task = $assignment->task;
        $this->ensureAuthor($actor, $task);
        abort_unless($request->isPending() && ! $task->isCancelled(), 422);

        DB::transaction(function () use ($actor, $request, $assignment, $approve) {
            $request->update([
                'status' => $approve ? TaskRequest::STATUS_APPROVED : TaskRequest::STATUS_REJECTED,
                'decided_by_user_id' => $actor->id,
                'decided_at' => now(),
            ]);

            if (! $approve) {
                return;
            }

            if ($request->type === TaskRequest::TYPE_DECLINE) {
                $assignment->update(['status' => TaskAssignee::STATUS_DECLINED, 'my_day_on' => null]);
            } else {
                $assignment->update(['due_date' => $request->requested_due_date]);
            }
        });

        $outcome = match (true) {
            ! $approve => 'رُفض '.$request->typeLabel(),
            $request->type === TaskRequest::TYPE_DECLINE => 'قُبل اعتذارك عن',
            default => 'مُدد موعد',
        };
        $suffix = $approve && $request->type === TaskRequest::TYPE_EXTEND
            ? ' إلى '.$request->requested_due_date->format('Y-m-d')
            : '';

        $this->notifications->raise(NotificationType::TodoRequestDecided, [
            'title' => "{$outcome} مهمة: {$task->title}{$suffix}",
            'url' => route('tasks.show', $task),
            'subject' => $task,
            'users' => [$assignment->user_id],
            'actor' => $actor,
        ]);
    }

    /**
     * Assigned tasks whose employees are waiting on the actor: requests to excuse or extend.
     */
    public function pendingDecisionsCount(User $user): int
    {
        return TaskRequest::query()
            ->where('status', TaskRequest::STATUS_PENDING)
            ->whereHas('assignment.task', fn ($task) => $task->where('created_by_user_id', $user->id)->whereNull('cancelled_at'))
            ->count();
    }

    /**
     * What the sidebar badge counts: tasks others assigned to the user and still open, plus requests awaiting their answer.
     */
    public function attentionCount(User $user): int
    {
        $assignedToMe = TaskAssignee::forUser($user)->active()
            ->whereHas('task', fn ($task) => $task->where('is_assigned', true)->where('created_by_user_id', '!=', $user->id))
            ->count();

        return $assignedToMe + $this->pendingDecisionsCount($user);
    }

    /**
     * The home card «مهامي اليوم»: what is late, due today or planned for today, and requests awaiting an answer.
     *
     * @return array{items: Collection<int, TaskAssignee>, overdue: int, due_today: int, my_day: int, pending_decisions: int, total: int}
     */
    public function todayFor(User $user): array
    {
        $relevant = TaskAssignee::forUser($user)->active()
            ->where(fn ($query) => $query->whereDate('due_date', '<=', today())->orWhereDate('my_day_on', today()))
            ->with('task.creator')
            ->orderByRaw('due_date is null')->orderBy('due_date')
            ->get();

        return [
            'items' => $relevant->take(5),
            'overdue' => $relevant->filter->isOverdue()->count(),
            'due_today' => $relevant->filter(fn (TaskAssignee $item) => $item->due_date?->isToday())->count(),
            'my_day' => $relevant->filter->isInMyDay()->count(),
            'pending_decisions' => $this->pendingDecisionsCount($user),
            'total' => $relevant->count(),
        ];
    }

    /**
     * Turn the chosen employees, departments and roles into user ids, enforcing what the actor may do.
     *
     * @param  array{assignee_ids?: list<int>, department_ids?: list<int>, role_ids?: list<int>}  $data
     * @return list<int>
     */
    private function resolveAssignees(User $actor, array $data, ?Task $existing = null): array
    {
        $userIds = array_map('intval', $data['assignee_ids'] ?? []);
        $departmentIds = array_map('intval', $data['department_ids'] ?? []);
        $roleIds = array_map('intval', $data['role_ids'] ?? []);

        if ($userIds === [] && $departmentIds === [] && $roleIds === []) {
            return [];
        }

        if (! $this->canAssign($actor)) {
            throw ValidationException::withMessages(['assignee_ids' => 'لا تملك صلاحية إسناد المهام لموظفين آخرين.']);
        }

        $assignable = $this->assignableUsers($actor);

        // Groups expand to the people in them the actor is allowed to reach.
        $groupIds = $assignable
            ->filter(fn (User $user) => in_array($user->productionDepartmentId(), $departmentIds, true)
                || in_array($user->role_id, $roleIds, true))
            ->pluck('id')->all();

        // People already on the task stay allowed, even if the actor's reach has changed since.
        $alreadyOnTask = $existing ? $existing->assignees()->pluck('user_id')->all() : [];
        $outOfReach = array_diff($userIds, $assignable->pluck('id')->all(), $alreadyOnTask, [$actor->id]);
        if ($outOfReach !== []) {
            throw ValidationException::withMessages(['assignee_ids' => 'لا تملك صلاحية إسناد مهام لبعض الموظفين المختارين.']);
        }

        if (($departmentIds !== [] || $roleIds !== []) && ! $this->canAssignMultiple($actor)) {
            throw ValidationException::withMessages(['assignee_ids' => 'لا تملك صلاحية الإسناد لقسم أو دور كامل.']);
        }

        $ids = array_values(array_unique(array_merge($userIds, $groupIds)));

        if (($departmentIds !== [] || $roleIds !== []) && $groupIds === [] && $userIds === []) {
            throw ValidationException::withMessages(['assignee_ids' => 'لا يوجد موظفون يمكنك الإسناد لهم في القسم أو الدور المختار.']);
        }

        if (count($ids) > 1 && ! $this->canAssignMultiple($actor)) {
            throw ValidationException::withMessages(['assignee_ids' => 'لا تملك صلاحية إسناد مهمة واحدة لأكثر من موظف.']);
        }

        return $ids;
    }

    /**
     * @param  list<int>  $userIds
     */
    private function addAssignees(Task $task, array $userIds, User $actor): void
    {
        if ($userIds === []) {
            return;
        }

        foreach ($userIds as $userId) {
            $task->assignees()->create([
                'user_id' => $userId,
                'due_date' => $task->due_date,
            ]);
        }

        $this->notifications->raise(NotificationType::TodoAssigned, [
            'title' => "مهمة جديدة من {$actor->name}: {$task->title}",
            'body' => $task->due_date ? 'موعد الإنجاز: '.$task->due_date->format('Y-m-d') : null,
            'url' => route('tasks.show', $task),
            'subject' => $task,
            'users' => $userIds,
            'actor' => $actor,
        ]);
    }

    /**
     * A list id only if the list belongs to the actor; anything else files the task under «المهام».
     */
    private function ownListId(User $actor, ?int $taskListId): ?int
    {
        if (! $taskListId) {
            return null;
        }

        return TaskList::whereKey($taskListId)->where('user_id', $actor->id)->value('id');
    }

    private function ensureAuthor(User $actor, Task $task): void
    {
        abort_unless($task->isCreatedBy($actor), 403);
    }

    private function ensureOwn(User $actor, TaskAssignee $assignment): void
    {
        abort_unless($assignment->user_id === $actor->id, 403);
    }

    private function ensureOwnActive(User $actor, TaskAssignee $assignment): void
    {
        $this->ensureOwn($actor, $assignment);
        abort_if($assignment->isDeclined() || $assignment->task->isCancelled(), 422);
    }

    /**
     * Roles the actor may assign to as a group: global roles, plus department roles of departments they reach.
     *
     * @return Collection<int, Role>
     */
    public function assignableRoles(User $actor): Collection
    {
        if (! $this->canAssignMultiple($actor)) {
            return collect();
        }

        $reachableRoleIds = $this->assignableUsers($actor)->pluck('role_id')->filter()->unique();

        return Role::whereIn('id', $reachableRoleIds)->orderBy('display_name')->get();
    }
}
