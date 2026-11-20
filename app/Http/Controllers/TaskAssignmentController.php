<?php

namespace App\Http\Controllers;

use App\Models\TaskAssignee;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * What an employee does with their own copy of a task (status, star, my day, list), and the
 * author sending a finished copy back.
 */
class TaskAssignmentController extends Controller
{
    public function __construct(private TaskService $tasks) {}

    public function status(Request $request, TaskAssignee $assignment): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(TaskAssignee::WORK_STATUSES))],
        ]);

        $this->tasks->setStatus($request->user(), $assignment, $validated['status']);

        return back()->with('success', $validated['status'] === TaskAssignee::STATUS_DONE ? 'أحسنت! أُنجزت المهمة.' : 'حُدّثت حالة المهمة.');
    }

    public function important(Request $request, TaskAssignee $assignment): RedirectResponse
    {
        $this->tasks->toggleImportant($request->user(), $assignment);

        return back();
    }

    public function myDay(Request $request, TaskAssignee $assignment): RedirectResponse
    {
        $this->tasks->toggleMyDay($request->user(), $assignment);

        return back();
    }

    public function moveToList(Request $request, TaskAssignee $assignment): RedirectResponse
    {
        $validated = $request->validate(['task_list_id' => ['nullable', 'integer']]);

        $this->tasks->moveToList($request->user(), $assignment, $validated['task_list_id'] ?? null);

        return back()->with('success', 'نُقلت المهمة.');
    }

    public function reopen(Request $request, TaskAssignee $assignment): RedirectResponse
    {
        $this->tasks->reopen($request->user(), $assignment);

        return back()->with('success', 'أُعيدت المهمة إلى الموظف.');
    }
}
