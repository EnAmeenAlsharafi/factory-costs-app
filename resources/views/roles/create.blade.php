@extends('layouts.app')

@section('title', 'إضافة دور لقسم ' . $department->name_ar . ' - مصنع مفروشات سدير')
@section('page-title', 'إضافة دور لقسم ' . $department->name_ar)

@section('content')
<div class="d-flex flex-column gap-4">
    @include('partials.page-header', [
        'title' => 'إضافة دور لقسم ' . $department->name_ar,
        'subtitle' => 'الدور الجديد يخص هذا القسم فقط، وصلاحياته لا تتجاوز مهام القسم.',
        'icon' => 'fas fa-user-tag',
        'breadcrumbs' => [
            ['title' => 'الأدوار والصلاحيات', 'url' => route('roles.index', ['department' => $department->id])],
            ['title' => 'دور جديد'],
        ],
    ])

    <form action="{{ route('roles.store') }}" method="POST" class="card p-4">
        @csrf
        <input type="hidden" name="department_id" value="{{ $department->id }}">

        <div class="row g-3">
            <div class="col-md-6">
                <label for="display_name" class="form-label">اسم الدور <span class="text-danger">*</span></label>
                <input type="text" name="display_name" id="display_name" value="{{ old('display_name') }}"
                       class="form-control @error('display_name') is-invalid @enderror"
                       placeholder="مثال: فني قص {{ $department->name_ar }}" required>
                @error('display_name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-6">
                <label for="copy_from_role_id" class="form-label">نسخ الصلاحيات من <span class="text-muted fs-8">(اختياري)</span></label>
                <select name="copy_from_role_id" id="copy_from_role_id" class="form-select @error('copy_from_role_id') is-invalid @enderror">
                    <option value="" {{ old('copy_from_role_id', $defaultCopyFromId) === '' ? 'selected' : '' }}>-- بدون صلاحيات مبدئية --</option>
                    @foreach ($department->roles as $existingRole)
                        <option value="{{ $existingRole->id }}" {{ (string) old('copy_from_role_id', $defaultCopyFromId) === (string) $existingRole->id ? 'selected' : '' }}>
                            {{ $existingRole->display_name }}
                        </option>
                    @endforeach
                </select>
                @error('copy_from_role_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <div class="form-text">يمكنك تعديل الصلاحيات بعد الإنشاء مباشرة.</div>
            </div>

            <div class="col-12">
                <label for="description" class="form-label">الوصف</label>
                <textarea name="description" id="description" rows="2" class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                @error('description')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('roles.index', ['department' => $department->id]) }}" class="btn btn-light border px-4">إلغاء</a>
            <button type="submit" class="btn btn-factory-warning px-4 fw-bold">
                <i class="fas fa-save" aria-hidden="true"></i> إنشاء الدور ومتابعة الصلاحيات
            </button>
        </div>
    </form>
</div>
@endsection
