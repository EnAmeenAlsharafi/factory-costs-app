<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskListRequest;
use App\Models\TaskList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * An employee's own task lists. Deleting a list keeps its tasks: they return to «المهام».
 */
class TaskListController extends Controller
{
    public function store(TaskListRequest $request): RedirectResponse
    {
        $list = TaskList::create(['user_id' => $request->user()->id] + $request->validated());

        return redirect()->route('tasks.index', ['view' => 'list-'.$list->id])->with('success', 'أُنشئت القائمة.');
    }

    public function update(TaskListRequest $request, TaskList $taskList): RedirectResponse
    {
        abort_unless($taskList->user_id === $request->user()->id, 403);
        $taskList->update($request->validated());

        return back()->with('success', 'غُيّر اسم القائمة.');
    }

    public function destroy(Request $request, TaskList $taskList): RedirectResponse
    {
        abort_unless($taskList->user_id === $request->user()->id, 403);
        $taskList->delete();

        return redirect()->route('tasks.index', ['view' => 'inbox'])->with('success', 'حُذفت القائمة، ونُقلت مهامها إلى «المهام».');
    }
}
