<?php

namespace App\Http\Controllers;

use App\Http\Requests\PersonalNoteRequest;
use App\Models\PersonalNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Private notes. Nobody but their author can read them, the administrator included.
 */
class PersonalNoteController extends Controller
{
    public function store(PersonalNoteRequest $request): RedirectResponse
    {
        PersonalNote::create(['user_id' => $request->user()->id] + $request->validated());

        return redirect()->route('tasks.index', ['view' => 'notes'])->with('success', 'حُفظت الملاحظة.');
    }

    public function update(PersonalNoteRequest $request, PersonalNote $note): RedirectResponse
    {
        abort_unless($note->user_id === $request->user()->id, 403);
        $note->update($request->validated() + ['is_pinned' => $request->boolean('is_pinned')]);

        return redirect()->route('tasks.index', ['view' => 'notes'])->with('success', 'حُفظت الملاحظة.');
    }

    public function pin(Request $request, PersonalNote $note): RedirectResponse
    {
        abort_unless($note->user_id === $request->user()->id, 403);
        $note->update(['is_pinned' => ! $note->is_pinned]);

        return back();
    }

    public function destroy(Request $request, PersonalNote $note): RedirectResponse
    {
        abort_unless($note->user_id === $request->user()->id, 403);
        $note->delete();

        return back()->with('success', 'حُذفت الملاحظة.');
    }
}
