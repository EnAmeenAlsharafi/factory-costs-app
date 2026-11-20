{{-- Private notes: a quick-add card and the notes, pinned first. --}}
<form action="{{ route('tasks.notes.store') }}" method="POST" class="card-factory p-3 mb-3">
    @csrf
    <label for="note-title" class="visually-hidden">عنوان الملاحظة</label>
    <input id="note-title" type="text" name="title" class="form-control mb-2" maxlength="150" placeholder="عنوان (اختياري)" value="{{ old('title') }}">
    <label for="note-body" class="visually-hidden">نص الملاحظة</label>
    <textarea id="note-body" name="body" class="form-control mb-2" rows="3" maxlength="5000" placeholder="اكتب ملاحظتك..." required>{{ old('body') }}</textarea>
    <div class="d-flex justify-content-between align-items-center gap-2">
        <div class="form-check m-0">
            <input class="form-check-input" type="checkbox" name="is_pinned" value="1" id="note-pin">
            <label class="form-check-label fs-7" for="note-pin">تثبيت في الأعلى</label>
        </div>
        <button type="submit" class="btn btn-factory-primary">حفظ الملاحظة</button>
    </div>
</form>

@if ($notes->isEmpty())
    <div class="card-factory empty-state" role="status">
        <i class="fas fa-note-sticky fs-2 mb-3 text-secondary" aria-hidden="true"></i>
        <h2 class="h6 fw-bold text-dark">لا توجد ملاحظات</h2>
        <p class="mb-0 fs-7">دوّن ما تريد تذكّره: أرقام، أفكار، متابعات. لا يراها أحد غيرك.</p>
    </div>
@else
    <div class="tasks-notes-grid">
        @foreach ($notes as $note)
            <article class="card-factory tasks-note {{ $note->is_pinned ? 'is-pinned' : '' }}" x-data="{ editing: false }">
                <div x-show="!editing">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                        <h2 class="h6 fw-bold text-dark mb-0">{{ $note->title ?: 'ملاحظة' }}</h2>
                        <div class="d-flex gap-1 flex-shrink-0">
                            <form action="{{ route('tasks.notes.pin', $note) }}" method="POST" class="m-0">
                                @csrf @method('PATCH')
                                <button type="submit" class="btn btn-sm btn-link p-1 {{ $note->is_pinned ? 'text-warning' : 'text-muted' }}"
                                        aria-pressed="{{ $note->is_pinned ? 'true' : 'false' }}" aria-label="تثبيت الملاحظة" title="{{ $note->is_pinned ? 'إلغاء التثبيت' : 'تثبيت' }}">
                                    <i class="fas fa-thumbtack" aria-hidden="true"></i>
                                </button>
                            </form>
                            <button type="button" class="btn btn-sm btn-link p-1 text-muted" @click="editing = true" aria-label="تعديل الملاحظة" title="تعديل">
                                <i class="fas fa-pen" aria-hidden="true"></i>
                            </button>
                            <form action="{{ route('tasks.notes.destroy', $note) }}" method="POST" class="m-0" onsubmit="return confirm('حذف الملاحظة نهائياً؟')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-link p-1 text-danger" aria-label="حذف الملاحظة" title="حذف">
                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <p class="mb-2 fs-7 tasks-note-body">{{ $note->body }}</p>
                    <span class="fs-8 text-muted">{{ $note->updated_at->diffForHumans() }}</span>
                </div>

                <form x-show="editing" x-cloak action="{{ route('tasks.notes.update', $note) }}" method="POST">
                    @csrf @method('PUT')
                    <input type="text" name="title" class="form-control form-control-sm mb-2" maxlength="150" value="{{ $note->title }}" aria-label="عنوان الملاحظة">
                    <textarea name="body" class="form-control form-control-sm mb-2" rows="4" maxlength="5000" required aria-label="نص الملاحظة">{{ $note->body }}</textarea>
                    <input type="hidden" name="is_pinned" value="{{ $note->is_pinned ? 1 : 0 }}">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-factory-primary">حفظ</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" @click="editing = false">إلغاء</button>
                    </div>
                </form>
            </article>
        @endforeach
    </div>
@endif
