@extends('layouts.app')

@section('title', 'إضافة مستخدم جديد - مصنع مفروشات سدير')
@section('page-title', 'إضافة مستخدم جديد للنظام')

@section('content')
<div class="d-flex flex-column gap-4 max-w-4xl mx-auto" style="max-width: 800px;">

    <!-- Breadcrumb back link -->
    <div class="d-flex align-items-center justify-content-between">
        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary btn-sm rounded-3">
            <i class="fas fa-arrow-right me-1"></i> العودة لسجل المستخدمين
        </a>
    </div>

    <!-- Form Card -->
    <div class="card bg-white border-0 shadow-sm rounded-4 p-4 p-md-5">
        <div class="border-bottom pb-3 mb-4">
            <h5 class="fw-bold text-dark mb-1">
                <i class="fas fa-user-plus text-warning me-2"></i> بيانات المستخدم الجديد
            </h5>
            <p class="text-muted fs-7 mb-0">قم بإدخال بيانات منسوب المصنع وتعيين دوره الوظيفي لتحديد صلاحياته في النظام.</p>
        </div>

        <form action="{{ route('users.store') }}" method="POST">
            @csrf

            <div class="row g-3">
                
                <!-- Full Name -->
                <div class="col-md-6">
                    <label for="name" class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                    <input type="text" 
                           name="name" 
                           id="name" 
                           class="form-control @error('name') is-invalid @enderror" 
                           value="{{ old('name') }}" 
                           placeholder="مثال: خالد محمد العتيبي" 
                           required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Username -->
                <div class="col-md-6">
                    <label for="username" class="form-label">اسم المستخدم للدخول (Username) <span class="text-danger">*</span></label>
                    <input type="text" 
                           name="username" 
                           id="username" 
                           class="form-control @error('username') is-invalid @enderror" 
                           value="{{ old('username') }}" 
                           placeholder="مثال: khaled.otb" 
                           required>
                    <small class="text-muted fs-8">أحرف إنجليزية وأرقام ونقاط أو شرطات فقط.</small>
                    @error('username')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Email (Optional) -->
                <div class="col-md-6">
                    <label for="email" class="form-label">البريد الإلكتروني <span class="text-muted fs-8">(اختياري)</span></label>
                    <input type="email" 
                           name="email" 
                           id="email" 
                           class="form-control @error('email') is-invalid @enderror" 
                           value="{{ old('email') }}" 
                           placeholder="employee@sadir-factory.com">
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Role Selection -->
                <div class="col-md-6">
                    <label for="role_id" class="form-label">الدور الوظيفي والصلاحيات <span class="text-danger">*</span></label>
                    <select name="role_id" id="role_id" class="form-select @error('role_id') is-invalid @enderror" required>
                        <option value="">-- اختر الدور الوظيفي للمستخدم --</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>
                                {{ $role->display_name }} - {{ $role->description }}
                            </option>
                        @endforeach
                    </select>
                    @error('role_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Department Selection -->
                <div class="col-md-6">
                    <label for="department_id" class="form-label">القسم التشغيلي / الورشة <span class="text-muted fs-8">(اختياري)</span></label>
                    <select name="department_id" id="department_id" class="form-select @error('department_id') is-invalid @enderror">
                        <option value="">-- بدون قسم محدد --</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept->id }}" {{ old('department_id') == $dept->id ? 'selected' : '' }}>
                                {{ $dept->name_ar }} ({{ $dept->code }})
                            </option>
                        @endforeach
                    </select>
                    @error('department_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Password -->
                <div class="col-md-6">
                    <label for="password" class="form-label">كلمة المرور <span class="text-danger">*</span></label>
                    <input type="password" 
                           name="password" 
                           id="password" 
                           class="form-control @error('password') is-invalid @enderror" 
                           placeholder="لا تقل عن 6 خانات" 
                           required>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Password Confirmation -->
                <div class="col-md-6">
                    <label for="password_confirmation" class="form-label">تأكيد كلمة المرور <span class="text-danger">*</span></label>
                    <input type="password" 
                           name="password_confirmation" 
                           id="password_confirmation" 
                           class="form-control" 
                           placeholder="أعد كتابة كلمة المرور" 
                           required>
                </div>

                <!-- Active Status -->
                <div class="col-12 mt-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') == '1' ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold text-dark fs-7" for="is_active">
                            تفعيل الحساب فور الإنشاء والسماح بتسجيل الدخول
                        </label>
                    </div>
                </div>

                <!-- Form Action Buttons -->
                <div class="col-12 d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('users.index') }}" class="btn btn-light border px-4">إلغاء</a>
                    <button type="submit" class="btn btn-factory-warning px-4">
                        <i class="fas fa-save me-1"></i> حفظ وتفعيل المستخدم
                    </button>
                </div>

            </div>
        </form>
    </div>

</div>
@endsection
