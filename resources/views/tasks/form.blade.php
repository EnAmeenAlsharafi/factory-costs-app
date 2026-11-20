@extends('layouts.app')

@php
    $isEdit = $task->exists;
    $showAssignees = ! $isEdit || $task->is_assigned;
    $selected = collect(old('assignee_ids', $selectedIds))->map(fn ($id) => (int) $id)->all();
    $people = $assignableUsers->map(fn ($member) => [
        'id' => $member->id,
        'name' => $member->name,
        'meta' => collect([$member->role?->display_name, $member->department?->name_ar])->filter()->unique()->join(' · '),
        'locked' => in_array($member->id, $doneIds, true),
    ])->values();
@endphp

@section('title', ($isEdit ? 'تعديل مهمة' : 'إسناد مهمة').' - مهامي')
@section('page-title', 'مهامي')

@section('content')
<div class="d-flex flex-column gap-3">
    <div>
        <a href="{{ $isEdit ? route('tasks.show', $task) : route('tasks.index', ['view' => 'assigned-by-me']) }}" class="text-decoration-none fs-7">
            <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i> رجوع
        </a>
    </div>

    <div class="page-header-card">
        <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">
            <i class="fas {{ $isEdit ? 'fa-pen' : 'fa-share-from-square' }} text-warning" aria-hidden="true"></i>
            {{ $isEdit ? 'تعديل المهمة' : 'إسناد مهمة' }}
        </h1>
        <p class="page-header-subtitle text-muted mb-0 fs-7">
            @if ($showAssignees)
                يصل كل موظف إشعار بالمهمة، ولكل واحد منهم حالته وموعده. {{ $canAssignMultiple ? 'يمكنك اختيار عدة موظفين أو قسماً أو دوراً كاملاً.' : 'صلاحيتك تسمح بموظف واحد لكل مهمة.' }}
            @else
                مهمة شخصية لا يراها غيرك.
            @endif
        </p>
    </div>

    <form action="{{ $isEdit ? route('tasks.update', $task) : route('tasks.store') }}" method="POST" class="row g-3">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <div class="{{ $showAssignees ? 'col-lg-6' : 'col-12' }}">
            <div class="card-factory p-3 h-100">
                <div class="mb-3">
                    <label for="title" class="form-label fw-bold">عنوان المهمة <span class="text-danger" aria-hidden="true">*</span></label>
                    <input id="title" type="text" name="title" class="form-control @error('title') is-invalid @enderror" maxlength="200" required
                           value="{{ old('title', $task->title) }}" placeholder="مثال: جرد مخزن الأقمشة">
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label for="details" class="form-label fw-bold">التفاصيل</label>
                    <textarea id="details" name="details" rows="5" class="form-control @error('details') is-invalid @enderror" maxlength="5000"
                              placeholder="ما المطلوب بالضبط؟ وأي معلومة يحتاجها المنفّذ.">{{ old('details', $task->details) }}</textarea>
                    @error('details')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="due_date" class="form-label fw-bold">تاريخ الاستحقاق</label>
                    <input id="due_date" type="date" name="due_date" class="form-control @error('due_date') is-invalid @enderror"
                           value="{{ old('due_date', $task->due_date?->toDateString()) }}" aria-describedby="due-help">
                    <div id="due-help" class="form-text">
                        {{ $isEdit && $task->is_assigned ? 'تغيير التاريخ يطبَّق على كل من لم ينجز المهمة بعد.' : 'اختياري. الموظف يطلب التمديد ولا يغيّر التاريخ بنفسه.' }}
                    </div>
                    @error('due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        @if ($showAssignees)
            <div class="col-lg-6">
                <fieldset class="card-factory p-3 h-100"
                          x-data="{
                              query: '',
                              people: @js($people),
                              selected: @js($selected),
                              multiple: @js($canAssignMultiple),
                              get matches() {
                                  const needle = window.normalizeArabic(this.query);
                                  return needle ? this.people.filter((person) => window.normalizeArabic(person.name + ' ' + person.meta).includes(needle)) : this.people;
                              },
                              toggle(person) {
                                  if (person.locked) { return; }
                                  if (this.selected.includes(person.id)) {
                                      this.selected = this.selected.filter((id) => id !== person.id);
                                  } else {
                                      this.selected = this.multiple ? [...this.selected, person.id] : [person.id];
                                  }
                              },
                          }">
                    <legend class="form-label fw-bold fs-6">إسناد إلى <span class="text-danger" aria-hidden="true">*</span></legend>
                    @error('assignee_ids')<div class="alert alert-danger py-2 fs-7">{{ $message }}</div>@enderror

                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="assignee_ids[]" :value="id">
                    </template>

                    @if ($canAssignMultiple && ($departments->isNotEmpty() || $roles->isNotEmpty()))
                        <div class="row g-2 mb-3">
                            @if ($departments->isNotEmpty())
                                <div class="col-sm-6">
                                    <label for="department_ids" class="form-label fs-8 text-muted mb-1">أو قسم كامل</label>
                                    <select id="department_ids" name="department_ids[]" class="form-select form-select-sm">
                                        <option value="">— بدون —</option>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->id }}" @selected(in_array($department->id, old('department_ids', [])))>{{ $department->name_ar }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            @if ($roles->isNotEmpty())
                                <div class="col-sm-6">
                                    <label for="role_ids" class="form-label fs-8 text-muted mb-1">أو دور كامل</label>
                                    <select id="role_ids" name="role_ids[]" class="form-select form-select-sm">
                                        <option value="">— بدون —</option>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}" @selected(in_array($role->id, old('role_ids', [])))>{{ $role->display_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="col-12 form-text mt-0">يُضاف موظفو القسم أو الدور إلى من تختارهم بالأسفل، كلٌّ بمهمته الخاصة.</div>
                        </div>
                    @endif

                    @if ($people->isEmpty())
                        <p class="text-muted fs-7 mb-0">لا يوجد موظفون ضمن نطاق صلاحيتك للإسناد.</p>
                    @else
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fs-8 text-muted" aria-live="polite">المختارون: <strong x-text="selected.length"></strong></span>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" x-show="selected.length" @click="selected = selected.filter((id) => people.find((p) => p.id === id)?.locked)">مسح الاختيار</button>
                        </div>
                        <label for="people-search" class="visually-hidden">ابحث عن موظف</label>
                        <input id="people-search" type="search" class="form-control form-control-sm mb-2" x-model="query" placeholder="ابحث بالاسم أو القسم أو الدور...">
                        <ul class="list-unstyled tasks-people mb-0" role="listbox" aria-label="الموظفون" :aria-multiselectable="multiple.toString()">
                            <template x-for="person in matches" :key="person.id">
                                <li role="option" :aria-selected="selected.includes(person.id).toString()"
                                    class="tasks-person" :class="{ 'is-selected': selected.includes(person.id), 'is-locked': person.locked }"
                                    tabindex="0" @click="toggle(person)" @keydown.enter.prevent="toggle(person)" @keydown.space.prevent="toggle(person)">
                                    <i class="fas" :class="selected.includes(person.id) ? 'fa-square-check text-primary' : 'fa-square text-muted'" aria-hidden="true"></i>
                                    <span class="min-w-0">
                                        <span class="d-block fw-bold text-dark" x-text="person.name"></span>
                                        <span class="d-block fs-8 text-muted" x-text="person.locked ? 'أنجز المهمة' : person.meta"></span>
                                    </span>
                                </li>
                            </template>
                            <li class="text-muted fs-7 p-2" x-show="matches.length === 0">لا يوجد موظف بهذا الاسم.</li>
                        </ul>
                    @endif
                </fieldset>
            </div>
        @endif

        <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-factory-primary px-4">
                <i class="fas {{ $isEdit ? 'fa-floppy-disk' : 'fa-paper-plane' }} ms-1" aria-hidden="true"></i>
                {{ $isEdit ? 'حفظ التعديلات' : 'إسناد وإبلاغ الموظفين' }}
            </button>
            <a href="{{ $isEdit ? route('tasks.show', $task) : route('tasks.index', ['view' => 'assigned-by-me']) }}" class="btn btn-outline-secondary">إلغاء</a>
        </div>
    </form>
</div>
@endsection
