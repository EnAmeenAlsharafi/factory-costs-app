@extends('layouts.app')

@section('title', 'أوامر الشراء (PO)')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800">
                <i class="fas fa-file-contract me-2 text-primary"></i>أوامر الشراء التجاري (Purchase Orders)
            </h1>
            <p class="text-muted small mb-0">متابعة وإصدار الاتفاقيات التجارية وتتبع عمليات استلام الخامات بالمخازن</p>
        </div>
        @can('purchasing.create_po')
        <a href="{{ route('purchasing.orders.create') }}" class="btn btn-primary rounded-pill px-4">
            <i class="fas fa-plus me-1"></i>أمر شراء جديد (PO)
        </a>
        @endcan
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-3">
            <form action="{{ route('purchasing.orders.index') }}" method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="رقم الأمر، مرجع المورد، اسم المورد...">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">جميع الحالات</option>
                        <option value="DRAFT" {{ request('status') === 'DRAFT' ? 'selected' : '' }}>مسودة</option>
                        <option value="APPROVED" {{ request('status') === 'APPROVED' ? 'selected' : '' }}>معتمد</option>
                        <option value="SENT" {{ request('status') === 'SENT' ? 'selected' : '' }}>مرسل للمورد</option>
                        <option value="PARTIALLY_RECEIVED" {{ request('status') === 'PARTIALLY_RECEIVED' ? 'selected' : '' }}>مستلم جزئياً</option>
                        <option value="RECEIVED" {{ request('status') === 'RECEIVED' ? 'selected' : '' }}>مستلم بالكامل</option>
                        <option value="CLOSED" {{ request('status') === 'CLOSED' ? 'selected' : '' }}>مغلق (محسوم المتبقي)</option>
                        <option value="CANCELLED" {{ request('status') === 'CANCELLED' ? 'selected' : '' }}>ملغى</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="supplier_id" class="form-select">
                        <option value="">جميع الموردين</option>
                        @foreach($suppliers as $sup)
                        <option value="{{ $sup->id }}" {{ request('supplier_id') == $sup->id ? 'selected' : '' }}>{{ $sup->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <button type="submit" class="btn btn-outline-primary w-100 rounded-pill">تصفية</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light small">
                    <tr>
                        <th>رقم أمر الشراء</th>
                        <th>المورد</th>
                        <th>تاريخ الأمر</th>
                        <th>تاريخ التسليم المتوقع</th>
                        <th>المستودع المستهدف</th>
                        <th>إجمالي أمر الشراء</th>
                        <th>الحالة</th>
                        <th class="text-end">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="small">
                    @forelse($purchaseOrders as $po)
                    <tr>
                        <td class="fw-bold text-primary">{{ $po->purchase_order_number }}</td>
                        <td class="fw-bold text-dark">{{ $po->supplier?->name }}</td>
                        <td>{{ $po->order_date->format('Y-m-d') }}</td>
                        <td>{{ $po->expected_delivery_date ? $po->expected_delivery_date->format('Y-m-d') : '-' }}</td>
                        <td><span class="badge bg-light text-dark border">{{ $po->warehouse?->name_ar }}</span></td>
                        <td class="fw-bold fs-6 text-dark">{{ number_format($po->total_amount, 2) }} SAR</td>
                        <td>
                            @php
                                $poBadges = [
                                    'DRAFT' => ['bg' => 'bg-secondary', 'label' => 'مسودة'],
                                    'APPROVED' => ['bg' => 'bg-info', 'label' => 'معتمد تجارياً'],
                                    'SENT' => ['bg' => 'bg-primary', 'label' => 'مرسل للمورد'],
                                    'PARTIALLY_RECEIVED' => ['bg' => 'bg-warning text-dark', 'label' => 'مستلم جزئياً'],
                                    'RECEIVED' => ['bg' => 'bg-success', 'label' => 'مستلم بالكامل'],
                                    'CLOSED' => ['bg' => 'bg-dark', 'label' => 'مغلق'],
                                    'CANCELLED' => ['bg' => 'bg-danger', 'label' => 'ملغى'],
                                ];
                                $st = $poBadges[$po->status] ?? ['bg' => 'bg-secondary', 'label' => $po->status];
                            @endphp
                            <span class="badge {{ $st['bg'] }} px-2 py-1">{{ $st['label'] }}</span>
                        </td>
                        <td class="text-end">
                            <a href="{{ route('purchasing.orders.show', $po) }}" class="btn btn-sm btn-outline-primary rounded-pill">
                                التفاصيل <i class="fas fa-arrow-left ms-1"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">لا توجد أوامر شراء مسجلة.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($purchaseOrders->hasPages())
        <div class="card-footer bg-white py-3">
            {{ $purchaseOrders->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
