<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskChangeRequestFormRequest;
use App\Models\TaskAssignee;
use App\Models\TaskRequest;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * An assignee asks to be excused or for a later date; the task's author answers.
 */
class TaskRequestController extends Controller
{
    public function __construct(private TaskService $tasks) {}

    public function store(TaskChangeRequestFormRequest $request, TaskAssignee $assignment): RedirectResponse
    {
        $validated = $request->validated();

        $this->tasks->submitRequest(
            $request->user(),
            $assignment,
            $validated['type'],
            $validated['reason'],
            $validated['requested_due_date'] ?? null,
        );

        return back()->with('success', 'أُرسل طلبك إلى من أسند المهمة، وستصلك إشعار بالرد.');
    }

    public function decide(Request $request, TaskRequest $taskRequest): RedirectResponse
    {
        $validated = $request->validate(['decision' => ['required', 'in:approve,reject']]);
        $approve = $validated['decision'] === 'approve';

        $this->tasks->decideRequest($request->user(), $taskRequest, $approve);

        return back()->with('success', $approve ? 'قُبل الطلب وأُبلغ الموظف.' : 'رُفض الطلب وأُبلغ الموظف.');
    }
}
