@extends('layouts.app')

@section('title', 'إعلان جديد - مصنع مفروشات سدير')
@section('page-title', 'إعلان جديد')

@section('content')
<div class="container-fluid px-4 py-3" x-data="{ target: '{{ old('target', 'everyone') }}' }">

    <div class="page-header-card mb-4">
        <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">
            <i class="fas fa-bullhorn text-warning" aria-hidden="true"></i> إعلان من الإدارة
        </h1>
        <p class="page-header-subtitle text-muted mb-0 fs-7">
            رسالة تصل جرس الإشعارات لدى من تختارهم: الجميع، أو قسم بعينه، أو موظفين محددين.
        </p>
    </div>

    <form action="{{ route('notifications.announce.store') }}" method="POST" class="row g-3">
        @csrf

        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="title" class="form-label fw-bold">نص الإعلان <span class="text-danger">*</span></label>
                        <input type="text" id="title" name="title" value="{{ old('title') }}" maxlength="180" required
                               class="form-control @error('title') is-invalid @enderror"
                               placeholder="مثال: إجازة يوم الخميس القادم لجميع الأقسام">
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-0">
                        <label for="body" class="form-label fw-bold">تفاصيل إضافية <span class="text-muted fw-normal fs-8">(اختياري)</span></label>
                        <textarea id="body" name="body" rows="4" maxlength="2000"
                                  class="form-control @error('body') is-invalid @enderror"
                                  placeholder="تفاصيل يحتاجها الموظف لتنفيذ ما ورد في الإعلان.">{{ old('body') }}</textarea>
                        @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <fieldset>
                        <legend class="form-label fw-bold fs-7">من يصله الإعلان؟</legend>

                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="target" id="target-everyone" value="everyone" x-model="target">
                            <label class="form-check-label" for="target-everyone">جميع موظفي النظام</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="target" id="target-department" value="department" x-model="target">
                            <label class="form-check-label" for="target-department">قسم محدد</label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="target" id="target-users" value="users" x-model="target">
                            <label class="form-check-label" for="target-users">موظفون محددون</label>
                        </div>
                    </fieldset>

                    <div x-show="target === 'department'" x-cloak class="mb-3">
                        <label for="department_id" class="form-label fs-8 text-muted">القسم</label>
                        <select id="department_id" name="department_id" class="form-select @error('department_id') is-invalid @enderror">
                            <option value="">— اختر القسم —</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>{{ $department->name_ar }}</option>
                            @endforeach
                        </select>
                        @error('department_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div x-show="target === 'users'" x-cloak>
                        <label for="user_ids" class="form-label fs-8 text-muted">الموظفون (اختر واحداً أو أكثر)</label>
                        <select id="user_ids" name="user_ids[]" multiple size="10"
                                class="form-select @error('user_ids') is-invalid @enderror">
                            @foreach ($employees as $employee)
                                <option value="{{ $employee->id }}" @selected(collect(old('user_ids'))->contains($employee->id))>
                                    {{ $employee->name }} — {{ $employee->role?->display_name }}{{ $employee->department ? ' / '.$employee->department->name_ar : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('user_ids')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="card-footer bg-white border-top d-flex gap-2">
                    <button type="submit" class="btn btn-warning fw-bold flex-grow-1">
                        <i class="fas fa-paper-plane ms-1" aria-hidden="true"></i> إرسال الإعلان
                    </button>
                    <a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
