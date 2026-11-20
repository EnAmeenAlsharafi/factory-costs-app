<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskFormRequest;
use App\Models\Department;
use App\Models\PersonalNote;
use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\TaskList;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * «مهامي»: the employee's lists (my day, important, planned, assigned to me, assigned by me, own lists)
 * and notes, plus writing, editing and cancelling tasks.
 */
class TaskController extends Controller
{
    /**
     * Smart lists in the order they appear.
     *
     * @var array<string, array{label: string, icon: string}>
     */
    public const VIEWS = [
        'my-day' => ['label' => 'يومي', 'icon' => 'fa-sun'],
        'important' => ['label' => 'مهم', 'icon' => 'fa-star'],
        'planned' => ['label' => 'مخطط له', 'icon' => 'fa-calendar-days'],
        'assigned-to-me' => ['label' => 'مُسندة إليّ', 'icon' => 'fa-user-check'],
        'assigned-by-me' => ['label' => 'أسندتُها', 'icon' => 'fa-share-from-square'],
        'inbox' => ['label' => 'المهام', 'icon' => 'fa-house'],
        'notes' => ['label' => 'ملاحظات', 'icon' => 'fa-note-sticky'],
    ];

    public function __construct(private TaskService $tasks) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->can('tasks.use'), 403);

        $lists = TaskList::where('user_id', $user->id)->orderBy('name')->get();
        $view = (string) $request->query('view', 'my-day');
        $currentList = null;

        if (str_starts_with($view, 'list-')) {
            $currentList = $lists->firstWhere('id', (int) substr($view, 5));
            abort_unless($currentList, 404);
        } elseif (! array_key_exists($view, self::VIEWS)) {
            $view = 'my-day';
        }

        $data = [
            'view' => $view,
            'views' => self::VIEWS,
            'lists' => $lists,
            'currentList' => $currentList,
            'counts' => $this->counts($user, $lists),
            'canAssign' => $this->tasks->canAssign($user),
            'title' => $currentList?->name ?? self::VIEWS[$view]['label'],
        ];

        if ($view === 'notes') {
            return view('tasks.index', $data + [
                'notes' => PersonalNote::where('user_id', $user->id)->orderByDesc('is_pinned')->latest('updated_at')->get(),
            ]);
        }

        if ($view === 'assigned-by-me') {
            return view('tasks.index', $data + [
                'authoredTasks' => Task::query()
                    ->where('created_by_user_id', $user->id)
                    ->where('is_assigned', true)
                    ->with(['assignees.user', 'assignees.pendingRequest'])
                    ->orderByRaw('cancelled_at is not null')
                    ->latest()
                    ->limit(100)
                    ->get(),
            ]);
        }

        $query = $this->viewQuery($user, $view, $currentList);

        return view('tasks.index', $data + [
            'openItems' => (clone $query)->whereIn('status', [TaskAssignee::STATUS_OPEN, TaskAssignee::STATUS_IN_PROGRESS])
                ->orderByRaw('due_date is null')->orderBy('due_date')->latest('id')->get(),
            'doneItems' => (clone $query)->where('status', TaskAssignee::STATUS_DONE)
                ->latest('completed_at')->limit(30)->get(),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->tasks->canAssign($user), 403);

        return view('tasks.form', $this->formData($user, new Task));
    }

    public function store(TaskFormRequest $request): RedirectResponse
    {
        $task = $this->tasks->create($request->user(), $request->validated());

        if ($task->is_assigned) {
            return redirect()->route('tasks.show', $task)->with('success', 'أُسندت المهمة وأُبلغ الموظفون بها.');
        }

        return back()->with('success', 'أُضيفت المهمة.');
    }

    public function show(Request $request, Task $task): View
    {
        $user = $request->user();
        $myAssignment = $task->assignees()->where('user_id', $user->id)->with('pendingRequest', 'requests.decidedBy')->first();
        abort_unless($task->isCreatedBy($user) || $myAssignment, 403);

        $task->load(['creator', 'assignees.user.department', 'assignees.pendingRequest', 'assignees.requests']);

        return view('tasks.show', [
            'task' => $task,
            'myAssignment' => $myAssignment,
            'isAuthor' => $task->isCreatedBy($user),
            'lists' => TaskList::where('user_id', $user->id)->orderBy('name')->get(),
        ]);
    }

    public function edit(Request $request, Task $task): View
    {
        $user = $request->user();
        abort_unless($task->isCreatedBy($user) && ! $task->isCancelled(), 403);

        return view('tasks.form', $this->formData($user, $task));
    }

    public function update(TaskFormRequest $request, Task $task): RedirectResponse
    {
        $this->tasks->update($request->user(), $task, $request->validated());

        return redirect()->route('tasks.show', $task)->with('success', 'حُفظت تعديلات المهمة.');
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        $wasAssigned = $task->is_assigned;
        $this->tasks->cancel($request->user(), $task);

        return $wasAssigned
            ? redirect()->route('tasks.show', $task)->with('success', 'أُلغيت المهمة، ولم تعد تظهر للموظفين.')
            : redirect()->route('tasks.index')->with('success', 'حُذفت المهمة.');
    }

    /**
     * The employee's own copies of tasks for one list, before splitting into open and done.
     */
    private function viewQuery(User $user, string $view, ?TaskList $list): Builder
    {
        $query = TaskAssignee::forUser($user)
            ->where('status', '!=', TaskAssignee::STATUS_DECLINED)
            ->whereHas('task', fn (Builder $task) => $task->whereNull('cancelled_at'))
            ->with(['task.creator', 'taskList', 'pendingRequest']);

        return match ($view) {
            'my-day' => $query->whereDate('my_day_on', today()),
            'important' => $query->where('is_important', true),
            'planned' => $query->whereNotNull('due_date'),
            'assigned-to-me' => $query->whereHas('task', fn (Builder $task) => $task->where('is_assigned', true)->where('created_by_user_id', '!=', $user->id)),
            'inbox' => $query->whereNull('task_list_id')->whereHas('task', fn (Builder $task) => $task->where('is_assigned', false)),
            default => $query->where('task_list_id', $list?->id),
        };
    }

    /**
     * Open-task counts shown beside each list.
     *
     * @return array<string, int>
     */
    private function counts(User $user, Collection $lists): array
    {
        $open = TaskAssignee::forUser($user)->active()->with('task:id,is_assigned,created_by_user_id')->get();

        $counts = [
            'my-day' => $open->filter(fn (TaskAssignee $item) => $item->isInMyDay())->count(),
            'important' => $open->where('is_important', true)->count(),
            'planned' => $open->whereNotNull('due_date')->count(),
            'assigned-to-me' => $open->filter(fn (TaskAssignee $item) => $item->task->is_assigned && $item->task->created_by_user_id !== $user->id)->count(),
            'assigned-by-me' => $this->tasks->pendingDecisionsCount($user),
            'inbox' => $open->filter(fn (TaskAssignee $item) => $item->task_list_id === null && ! $item->task->is_assigned)->count(),
            'notes' => 0,
        ];

        foreach ($lists as $list) {
            $counts['list-'.$list->id] = $open->where('task_list_id', $list->id)->count();
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(User $user, Task $task): array
    {
        $assignable = $this->tasks->assignableUsers($user);
        // Someone already on the task stays listed even if they are now out of the author's reach.
        if ($task->exists) {
            $assignable = $assignable->merge(
                User::whereIn('id', $task->assignees()->pluck('user_id'))->whereKeyNot($user->id)->with(['role', 'department'])->get()
            )->unique('id')->sortBy('name')->values();
        }
        $canAssignMultiple = $this->tasks->canAssignMultiple($user);

        return [
            'task' => $task,
            'assignableUsers' => $assignable,
            'selectedIds' => $task->exists ? $task->assignees()->pluck('user_id')->all() : [],
            'doneIds' => $task->exists ? $task->assignees()->where('status', TaskAssignee::STATUS_DONE)->pluck('user_id')->all() : [],
            'canAssignMultiple' => $canAssignMultiple,
            'departments' => $canAssignMultiple
                ? Department::whereIn('id', $assignable->map(fn (User $member) => $member->productionDepartmentId())->filter()->unique())->orderBy('name_ar')->get()
                : collect(),
            'roles' => $this->tasks->assignableRoles($user),
        ];
    }
}
