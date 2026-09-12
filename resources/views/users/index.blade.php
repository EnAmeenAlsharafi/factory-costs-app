@extends('layouts.app')

@section('title', 'إدارة المستخدمين - مصنع مفروشات سدير')
@section('page-title', 'سجل المستخدمين والموظفين')

@section('content')
<div class="d-flex flex-column gap-4">

    <!-- Page Header Component -->
    @include('partials.page-header', [
        'title' => 'إدارة المستخدمين والموظفين',
        'subtitle' => 'إدارة حسابات منسوبي المصنع، تعيين الأدوار التشغيلية، ومتابعة حالة التفعيل وسجل النشاط.',
        'icon' => 'fas fa-users-cog',
        'breadcrumbs' => [
            ['title' => 'إدارة النظام'],
            ['title' => 'المستخدمون']
        ],
        'actionUrl' => route('users.create'),
        'actionText' => 'إضافة مستخدم جديد',
        'actionIcon' => 'fas fa-user-plus',
        'actionPermission' => 'users.create'
    ])

    <!-- Filter Toolbar Card -->
    <div class="card-factory p-3">
        <form action="{{ route('users.index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="fas fa-search"></i></span>
                    <input type="text" 
                           name="search" 
                           value="{{ request('search') }}" 
                           class="form-control border-start-0 ps-2" 
                           placeholder="بحث بالاسم، اسم المستخدم، أو البريد...">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <select name="role_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">كافة الأدوار الوظيفية</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}" {{ request('role_id') == $role->id ? 'selected' : '' }}>
                            {{ $role->display_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">كافة الحالات</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>نشط فقط</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>معطل فقط</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-dark flex-grow-1">تصفية</button>
                @if (request()->hasAny(['search', 'role_id', 'status']))
                    <a href="{{ route('users.index') }}" class="btn btn-sm btn-light border text-secondary" title="إعادة ضبط">
                        <i class="fas fa-undo"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Users Table Card -->
    <div class="table-factory-wrapper">
        <div class="table-responsive">
            <table class="table table-factory mb-0">
                <thead>
                    <tr>
                        <th># المعرف</th>
                        <th>الموظف / الاسم</th>
                        <th>اسم الدخول (Username)</th>
                        <th>الدور الوظيفي</th>
                        <th>حالة الحساب</th>
                        <th>آخر تسجيل دخول</th>
                        <th>تاريخ الإضافة</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td class="fw-bold text-muted fs-7">#{{ $user->id }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="avatar-circle bg-light border text-dark fw-bold rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; font-size: 0.85rem;">
                                        {{ mb_substr($user->name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold text-dark">{{ $user->name }}</div>
                                        @if ($user->email)
                                            <small class="text-muted fs-8 d-block">{{ $user->email }}</small>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                <code class="code-badge">{{ $user->username }}</code>
                            </td>
                            <td>
                                @if ($user->role)
                                    <span class="badge bg-secondary bg-opacity-10 text-dark border">
                                        {{ $user->role->display_name }}
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border">غير محدد</span>
                                @endif
                                @if ($user->department)
                                    <div class="mt-1">
                                        <span class="badge bg-light text-secondary border fs-8">
                                            <i class="fas fa-building text-warning me-1"></i>{{ $user->department->name_ar }}
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if ($user->is_active)
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                        <i class="fas fa-check-circle me-1"></i> نشط
                                    </span>
                                @else
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">
                                        <i class="fas fa-ban me-1"></i> معطل
                                    </span>
                                @endif
                            </td>
                            <td class="text-muted fs-8">
                                @if ($user->last_login_at)
                                    {{ $user->last_login_at->locale('ar')->diffForHumans() }}
                                @else
                                    <span class="text-muted opacity-50">لم يسجل دخول بعد</span>
                                @endif
                            </td>
                            <td class="text-muted fs-8">
                                {{ $user->created_at->format('Y-m-d') }}
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex align-items-center gap-1">
                                    @can('users.update')
                                        <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-light border text-secondary p-1.5" title="تعديل المستخدم">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    @endcan

                                    @if ($user->id !== auth()->id())
                                        @if ($user->is_active)
                                            @can('users.deactivate')
                                                <form action="{{ route('users.toggle-status', $user) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" 
                                                            class="btn btn-sm btn-light border text-danger p-1.5" 
                                                            title="تعطيل الحساب"
                                                            onclick="return confirm('هل أنت متأكد من رغبتك في تعطيل هذا الحساب ومنع المستخدم من تسجيل الدخول؟');">
                                                        <i class="fas fa-user-slash"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        @else
                                            @can('users.activate')
                                                <form action="{{ route('users.toggle-status', $user) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" 
                                                            class="btn btn-sm btn-light border text-success p-1.5" 
                                                            title="تفعيل الحساب"
                                                            onclick="return confirm('هل ترغب في إعادة تفعيل هذا الحساب والسماح له بالدخول مجدداً؟');">
                                                        <i class="fas fa-user-check"></i>
                                                    </button>
                                                </form>
                                            @endcan
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="fas fa-users-slash fs-1 d-block mb-3 text-secondary opacity-50"></i>
                                لا توجد سجلات مستخدمين تطابق معايير البحث المحددة.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="p-3 border-top d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <div class="text-muted fs-8">
                    عرض {{ $users->firstItem() }} إلى {{ $users->lastItem() }} من أصل {{ $users->total() }} مستخدم
                </div>
                <div>
                    {{ $users->links() }}
                </div>
            </div>
        @endif
    </div>

</div>
@endsection
