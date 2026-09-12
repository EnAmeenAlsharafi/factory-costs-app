@extends('layouts.app')

@section('title', 'إدارة العملاء - مصنع مفروشات سدير')
@section('page-title', 'سجل العملاء والشركاء')

@section('content')
<div class="d-flex flex-column gap-4">

    <!-- Page Header Component -->
    @include('partials.page-header', [
        'title' => 'سجل العملاء (Customers)',
        'subtitle' => 'إدارة بيانات عملاء المعارض، الموزعين بالجملة، والطلبات الخاصة، ومتابعة حدود الائتمان وقنوات البيع.',
        'icon' => 'fas fa-users',
        'breadcrumbs' => [
            ['title' => 'البيانات الأساسية'],
            ['title' => 'سجل العملاء']
        ],
        'actionUrl' => route('customers.create'),
        'actionText' => 'إضافة عميل جديد',
        'actionIcon' => 'fas fa-user-plus',
        'actionPermission' => 'customers.create'
    ])

    <!-- Filter Card -->
    <div class="card-factory p-3">
        <form action="{{ route('customers.index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light text-muted border-end-0"><i class="fas fa-search"></i></span>
                    <input type="text" 
                           name="search" 
                           value="{{ request('search') }}" 
                           class="form-control border-start-0 ps-2" 
                           placeholder="بحث بالرمز، الاسم، الجوال، أو السجل...">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <select name="customer_type_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">كافة التصنيفات</option>
                    @foreach ($customerTypes as $type)
                        <option value="{{ $type->id }}" {{ request('customer_type_id') == $type->id ? 'selected' : '' }}>
                            {{ $type->name_ar }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <select name="sales_channel_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">كافة قنوات البيع</option>
                    @foreach ($salesChannels as $channel)
                        <option value="{{ $channel->id }}" {{ request('sales_channel_id') == $channel->id ? 'selected' : '' }}>
                            {{ $channel->name_ar }}
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
            <div class="col-6 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-dark flex-grow-1">تصفية</button>
                @if (request()->hasAny(['search', 'customer_type_id', 'sales_channel_id', 'status', 'credit']))
                    <a href="{{ route('customers.index') }}" class="btn btn-sm btn-light border text-secondary" title="إعادة ضبط">
                        <i class="fas fa-undo"></i>
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Customers Table Card -->
    <div class="table-factory-wrapper">
        <div class="table-responsive">
            <table class="table table-factory mb-0">
                <thead>
                    <tr>
                        <th>رمز العميل</th>
                        <th>اسم العميل</th>
                        <th>التصنيف</th>
                        <th>قناة البيع</th>
                        <th>رقم الجوال</th>
                        <th>المدينة</th>
                        <th>الائتمان</th>
                        <th>الحالة</th>
                        <th class="text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td>
                                <a href="{{ route('customers.show', $customer) }}" class="text-decoration-none">
                                    <code class="code-badge">{{ $customer->customer_code }}</code>
                                </a>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $customer->name }}</div>
                                @if ($customer->commercial_name && $customer->commercial_name !== $customer->name)
                                    <small class="text-muted fs-8 d-block">{{ $customer->commercial_name }}</small>
                                @endif
                            </td>
                            <td>
                                @if ($customer->customerType)
                                    <span class="badge bg-light text-dark border">{{ $customer->customerType->name_ar }}</span>
                                @else
                                    <span class="text-muted fs-8">غير محدد</span>
                                @endif
                            </td>
                            <td>
                                @if ($customer->defaultSalesChannel)
                                    <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25">
                                        {{ $customer->defaultSalesChannel->name_ar }}
                                    </span>
                                @else
                                    <span class="text-muted fs-8">-</span>
                                @endif
                            </td>
                            <td>
                                <span dir="ltr">{{ $customer->mobile ?? $customer->phone ?? '-' }}</span>
                            </td>
                            <td>
                                {{ $customer->city ?? '-' }}
                            </td>
                            <td>
                                @if ($customer->is_credit_customer)
                                    <span class="badge bg-info bg-opacity-10 text-primary border border-info border-opacity-25" title="الحد الائتماني: {{ number_format($customer->credit_limit, 2) }} ر.س">
                                        <i class="fas fa-credit-card me-1"></i> ائتماني
                                        ({{ number_format($customer->credit_limit, 0) }} ر.س)
                                    </span>
                                @else
                                    <span class="badge bg-light text-muted border">نقدي</span>
                                @endif
                            </td>
                            <td>
                                @if ($customer->is_active)
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1">
                                        <i class="fas fa-check-circle me-1"></i> نشط
                                    </span>
                                @else
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">
                                        <i class="fas fa-ban me-1"></i> معطل
                                    </span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <a href="{{ route('customers.show', $customer) }}" class="btn btn-sm btn-light border text-secondary p-1.5" title="عرض التفاصيل">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    @can('customers.update')
                                        <a href="{{ route('customers.edit', $customer) }}" class="btn btn-sm btn-light border text-secondary p-1.5" title="تعديل العميل">
                                            <i class="fas fa-edit"></i>
                                        </a>

                                        <form action="{{ route('customers.toggle-status', $customer) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" 
                                                    class="btn btn-sm btn-light border p-1.5 {{ $customer->is_active ? 'text-danger' : 'text-success' }}" 
                                                    title="{{ $customer->is_active ? 'تعطيل العميل' : 'تفعيل العميل' }}"
                                                    onclick="return confirm('{{ $customer->is_active ? 'هل أنت متأكد من تعطيل هذا العميل؟ لن يظهر في القوائم النشطة.' : 'هل ترغب في إعادة تفعيل هذا العميل؟' }}');">
                                                <i class="fas {{ $customer->is_active ? 'fa-user-slash' : 'fa-user-check' }}"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="fas fa-users-slash fs-1 d-block mb-3 text-secondary opacity-50"></i>
                                لا توجد سجلات عملاء مطابقة للبحث الحالي.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($customers->hasPages())
            <div class="p-3 border-top d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <div class="text-muted fs-8">
                    عرض {{ $customers->firstItem() }} إلى {{ $customers->lastItem() }} من إجمالي {{ $customers->total() }} عميل
                </div>
                <div>
                    {{ $customers->links() }}
                </div>
            </div>
        @endif
    </div>

</div>
@endsection
