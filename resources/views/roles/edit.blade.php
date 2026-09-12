@extends('layouts.app')

@section('title', 'تعديل مصفوفة صلاحيات: ' . $role->display_name . ' - مصنع مفروشات سدير')
@section('page-title', 'تخصيص صلاحيات الدور: ' . $role->display_name)

@section('content')
<div class="d-flex flex-column gap-4">
    @include('partials.page-header', [
        'title' => 'تعديل صلاحيات ' . $role->display_name,
        'subtitle' => 'حدد الصلاحيات الممنوحة للدور. تظهر المفاتيح التقنية كمرجع ثانوي فقط.',
        'icon' => 'fas fa-sliders-h',
        'breadcrumbs' => [
            ['title' => 'الأدوار والصلاحيات', 'url' => route('roles.index')],
            ['title' => $role->display_name],
        ],
    ])

    @php
        $moduleLabels = [
            'users' => 'المستخدمون',
            'roles' => 'الأدوار والصلاحيات',
            'customers' => 'العملاء',
            'quotations' => 'عروض الأسعار',
            'orders' => 'طلبات العملاء',
            'production' => 'الإنتاج',
            'inventory' => 'المخزون',
            'materials' => 'المواد الخام',
            'products' => 'المنتجات',
            'suppliers' => 'الموردون',
            'costing' => 'التكاليف',
            'delivery' => 'التوصيل',
            'reports' => 'التقارير',
            'sales_channels' => 'قنوات البيع',
            'customer_types' => 'أنواع العملاء',
            'units' => 'وحدات القياس',
            'departments' => 'أقسام المصنع',
        ];
    @endphp

    <form action="{{ route('roles.update', $role) }}"
          method="POST"
          x-data="{ selectedCount: {{ count($rolePermissionIds) }} }"
          @permissions-changed.window="selectedCount = $event.detail.count">
        @csrf
        @method('PUT')

        <div class="row g-3">
            @foreach ($groupedPermissions as $module => $permissions)
                <div class="col-12 col-md-6 col-xl-4"
                     x-data="{
                         selected: 0,
                         total: {{ $permissions->count() }},
                         init() { this.sync(); },
                         boxes() { return [...this.$el.querySelectorAll('.permission-checkbox')]; },
                         sync() {
                             this.selected = this.boxes().filter((box) => box.checked).length;
                             this.$refs.selectAll.checked = this.selected === this.total;
                             this.$refs.selectAll.indeterminate = this.selected > 0 && this.selected < this.total;
                             this.$dispatch('permissions-changed', { count: document.querySelectorAll('.permission-checkbox:checked').length });
                         },
                         toggleAll() {
                             const checked = this.$refs.selectAll.checked;
                             this.boxes().forEach((box) => { box.checked = checked; });
                             this.sync();
                         }
                     }">
                    <fieldset class="permission-card card h-100 p-3">
                        <legend class="float-none w-100 px-0 pb-2 mb-2 border-bottom fs-6">
                            <span class="d-flex align-items-start justify-content-between gap-2">
                                <span>
                                    <strong class="d-block"><i class="fas fa-layer-group text-warning me-1" aria-hidden="true"></i>{{ $moduleLabels[$module] ?? $module }}</strong>
                                    <small class="permission-module-key d-block text-muted">{{ $module }}</small>
                                </span>
                                <span class="badge text-bg-light border"><span x-text="selected"></span>/{{ $permissions->count() }}</span>
                            </span>
                        </legend>

                        <label class="d-flex align-items-center gap-2 py-2 mb-2 border-bottom fw-semibold">
                            <input x-ref="selectAll" type="checkbox" class="form-check-input m-0" @change="toggleAll()">
                            <span>تحديد كل صلاحيات المجموعة</span>
                        </label>

                        <div class="d-flex flex-column gap-1">
                            @foreach ($permissions as $permission)
                                <div class="form-check">
                                    <input class="permission-checkbox form-check-input"
                                           type="checkbox"
                                           name="permissions[]"
                                           value="{{ $permission->id }}"
                                           id="perm_{{ $permission->id }}"
                                           @change="sync()"
                                           {{ in_array($permission->id, $rolePermissionIds, true) ? 'checked' : '' }}>
                                    <label class="form-check-label text-dark" for="perm_{{ $permission->id }}">
                                        <span class="d-block">{{ $permission->display_name }}</span>
                                        <code class="d-block text-muted fs-8">{{ $permission->name }}</code>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </fieldset>
                </div>
            @endforeach
        </div>

        <div class="permission-save-bar mt-4">
            <span class="save-summary me-auto text-muted fs-7" role="status" aria-live="polite">
                <i class="fas fa-shield-halved text-success me-1" aria-hidden="true"></i>
                المحدد: <strong x-text="selectedCount"></strong> صلاحية
            </span>
            <a href="{{ route('roles.index') }}" class="btn btn-light border px-4">إلغاء</a>
            <button type="submit" class="btn btn-factory-warning px-4 fw-bold">
                <i class="fas fa-save" aria-hidden="true"></i> حفظ الصلاحيات
            </button>
        </div>
    </form>
</div>
@endsection
