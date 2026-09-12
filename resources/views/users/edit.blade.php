@extends('layouts.app')

@section('title', 'تعديل المستخدم - مصنع مفروشات سدير')
@section('page-title', 'تعديل بيانات المستخدم: ' . $user->name)

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
        <div class="border-bottom pb-3 mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold text-dark mb-1">
                    <i class="fas fa-user-edit text-warning me-2"></i> تعديل بيانات الموظف
                </h5>
                <p class="text-muted fs-7 mb-0">تحديث البيانات الوظيفية أو تغيير الصلاحيات وإعادة تعيين كلمة المرور.</p>
            </div>
            <span class="badge bg-light text-dark border">المعرف: #{{ $user->id }}</span>
        </div>

        <form action="{{ route('users.update', $user) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row g-3">
                
                <!-- Full Name -->
                <div class="col-md-6">
                    <label for="name" class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                    <input type="text" 
                           name="name" 
                           id="name" 
                           class="form-control @error('name') is-invalid @enderror" 
                           value="{{ old('name', $user->name) }}" 
                           required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Username -->
                <div class="col-md-6">
                    <label for="username" class="form-label">اسم المستخدم للدخول <span class="text-danger">*</span></label>
                    <input type="text" 
                           name="username" 
                           id="username" 
                           class="form-control @error('username') is-invalid @enderror" 
                           value="{{ old('username', $user->username) }}" 
                           required>
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
                           value="{{ old('email', $user->email) }}">
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Role Selection -->
                <div class="col-md-6">
                    <label for="role_id" class="form-label">الدور الوظيفي <span class="text-danger">*</span></label>
                    <select name="role_id" id="role_id" class="form-select @error('role_id') is-invalid @enderror" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" {{ old('role_id', $user->role_id) == $role->id ? 'selected' : '' }}>
                                {{ $role->display_name }} ({{ $role->name }})
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
                            <option value="{{ $dept->id }}" {{ old('department_id', $user->department_id) == $dept->id ? 'selected' : '' }}>
                                {{ $dept->name_ar }} ({{ $dept->code }})
                            </option>
                        @endforeach
                    </select>
                    @error('department_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Password Reset Section Header -->
                <div class="col-12 mt-4 pt-3 border-top">
                    <h6 class="fw-bold text-dark mb-1"><i class="fas fa-key text-warning me-1"></i> إعادة تعيين كلمة المرور</h6>
                    <small class="text-muted">اترك هذه الحقول فارغة إذا كنت لا ترغب في تغيير كلمة المرور الحالية.</small>
                </div>

                <!-- New Password -->
                <div class="col-md-6">
                    <label for="password" class="form-label">كلمة المرور الجديدة</label>
                    <input type="password" 
                           name="password" 
                           id="password" 
                           class="form-control @error('password') is-invalid @enderror" 
                           placeholder="اتركها فارغة للإبقاء على الحالية">
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <!-- New Password Confirmation -->
                <div class="col-md-6">
                    <label for="password_confirmation" class="form-label">تأكيد كلمة المرور الجديدة</label>
                    <input type="password" 
                           name="password_confirmation" 
                           id="password_confirmation" 
                           class="form-control" 
                           placeholder="أعد إدخال كلمة المرور">
                </div>

                <!-- Active Status -->
                <div class="col-12 mt-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" 
                               type="checkbox" 
                               name="is_active" 
                               id="is_active" 
                               value="1" 
                               {{ old('is_active', $user->is_active) ? 'checked' : '' }}
                               {{ $user->id === auth()->id() ? 'disabled' : '' }}>
                        <label class="form-check-label fw-semibold text-dark fs-7" for="is_active">
                            حالة الحساب (نشط ومصرح له بالدخول)
                        </label>
                        @if ($user->id === auth()->id())
                            <small class="text-muted d-block fs-8">لا يمكنك تعطيل حسابك الشخصي أثناء تسجيل الدخول به.</small>
                            <input type="hidden" name="is_active" value="1">
                        @endif
                    </div>
                </div>

                <!-- Form Action Buttons -->
                <div class="col-12 d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="{{ route('users.index') }}" class="btn btn-light border px-4">إلغاء</a>
                    <button type="submit" class="btn btn-factory-warning px-4">
                        <i class="fas fa-save me-1"></i> حفظ التعديلات
                    </button>
                </div>

            </div>
        </form>
    </div>

</div>
@endsection
