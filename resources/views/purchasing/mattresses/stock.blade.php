@extends('layouts.app')

@section('title', 'مخزون المراتب - مصنع مفروشات سدير')
@section('page-title', 'المراتب')

@section('content')
@php
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    $lowRows = $rows->where('is_low', true);
    $canManage = auth()->user()->can('purchasing.create_po');
    $supply = app(\App\Services\ReadyProductSupplyService::class);
@endphp

<div class="d-flex flex-column gap-3">
    @include('purchasing.mattresses.partials.tabs', ['subtitle' => 'كم لدينا وكم لدى المورد من كل مقاس، وما المحجوز للطلبات المفتوحة. يُنبَّه عند الوصول للحد الأدنى.'])

    @if ($lowRows->isNotEmpty())
        <div class="card-factory pipeline-result is-partial" role="alert">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <p class="fw-bold mb-1"><i class="fas fa-triangle-exclamation text-warning me-1" aria-hidden="true"></i> {{ $lowRows->count() }} مقاس وصل للحد الأدنى أو أقل</p>
                    <p class="fs-7 mb-0">
                        @foreach ($lowRows->take(4) as $row)
                            {{ $supply->productName($row['setting']) }} {{ $row['setting']->material->ready_option_label }}: يتبقى <strong>{{ $qty($row['available']) }}</strong>@if (! $loop->last)، @endif
                        @endforeach
                        @if ($lowRows->count() > 4) وغيرها. @endif
                    </p>
                </div>
                @if ($canManage)
                    <a href="{{ route('purchasing.mattresses.production') }}" class="btn btn-warning fw-bold"><i class="fas fa-industry me-1" aria-hidden="true"></i> طلب إنتاج من المورد</a>
                @endif
            </div>
        </div>
    @endif

    <div class="row g-3">
        @foreach ([
            ['المقاسات المتابَعة', $rows->count(), 'fa-layer-group', 'primary'],
            ['عند الحد الأدنى', $lowRows->count(), 'fa-triangle-exclamation', $lowRows->isNotEmpty() ? 'danger' : 'success'],
            ['لدينا في المستودع', $qty($rows->sum('on_hand')), 'fa-warehouse', 'info'],
            ['لدى الموردين', $qty($rows->sum('at_supplier')), 'fa-truck-field', 'warning'],
        ] as [$label, $value, $icon, $tone])
            <div class="col-6 col-lg-3">
                <div class="card-factory p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fs-7 text-muted">{{ $label }}</span>
                        <span class="status-chip status-chip-{{ $tone }}"><i class="fas {{ $icon }}" aria-hidden="true"></i></span>
                    </div>
                    <div class="fs-3 fw-bold text-dark mt-1">{{ $value }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <section class="table-factory-wrapper" aria-labelledby="stock-heading">
        <div class="pipeline-list-head">
            <h2 id="stock-heading" class="h6 fw-bold mb-0">رصيد كل مقاس</h2>
            <span class="fs-8 text-muted">المتاح = لدينا + لدى المورد − المحجوز للطلبات المفتوحة</span>
        </div>
        @if ($rows->isEmpty())
            <div class="empty-state" role="status">
                <i class="fas fa-bed fs-2 mb-3 d-block opacity-50" aria-hidden="true"></i>
                <p class="fw-semibold mb-1">لا توجد مقاسات مراتب متابَعة بعد.</p>
                <p class="fs-7 mb-0">أضف مقاسات المراتب من قسم «إضافة مقاسات للمتابعة» أدناه، ثم حدد لكل مقاس مورده وحدّه الأدنى.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-factory table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>المرتبة / المقاس</th>
                            <th>المورد</th>
                            <th class="text-center">لدينا</th>
                            <th class="text-center">لدى المورد</th>
                            <th class="text-center">محجوز للطلبات</th>
                            <th class="text-center">المتاح</th>
                            <th class="text-center">الحد الأدنى</th>
                            <th>الحالة</th>
                            @if ($canManage)<th class="text-end">إعداد</th>@endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php $setting = $row['setting']; @endphp
                            <tr @class(['table-warning' => $row['is_low']])>
                                <td>
                                    <div class="fw-bold text-dark">{{ $supply->productName($setting) }}</div>
                                    <span class="status-chip status-chip-secondary"><i class="fas fa-ruler-combined" aria-hidden="true"></i> <bdi>{{ $setting->material->ready_option_label ?: 'مقاس واحد' }}</bdi></span>
                                </td>
                                <td>{{ $setting->supplier?->name ?? '—' }}@if (! $setting->supplier)<div class="fs-8 text-danger">حدد المورد</div>@endif</td>
                                <td class="text-center fw-semibold">{{ $qty($row['on_hand']) }}</td>
                                <td class="text-center fw-semibold">{{ $qty($row['at_supplier']) }}@if ($row['on_order'] > 0)<div class="fs-8 text-muted">منها {{ $qty($row['on_order']) }} قيد التوريد</div>@endif</td>
                                <td class="text-center">{{ $qty($row['reserved']) }}</td>
                                <td class="text-center fs-6 fw-bold {{ $row['is_low'] ? 'text-danger' : 'text-success' }}">{{ $qty($row['available']) }}</td>
                                <td class="text-center">{{ $setting->reorder_level }}<div class="fs-8 text-muted">إنتاج ≥ {{ $setting->production_batch_size }}</div></td>
                                <td>
                                    @if ($row['is_low'])
                                        <span class="status-chip status-chip-danger"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> اطلب إنتاج {{ $row['suggested'] }}</span>
                                    @else
                                        <span class="status-chip status-chip-success"><i class="fas fa-check" aria-hidden="true"></i> كافٍ</span>
                                    @endif
                                </td>
                                @if ($canManage)
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary text-nowrap" data-bs-toggle="modal" data-bs-target="#setting-{{ $setting->id }}">
                                            <i class="fas fa-sliders" aria-hidden="true"></i> إعداد
                                        </button>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @if ($canManage)
        @foreach ($rows as $row)
            @php $setting = $row['setting']; @endphp
            <div class="modal fade" id="setting-{{ $setting->id }}" tabindex="-1" aria-labelledby="setting-title-{{ $setting->id }}" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" action="{{ route('purchasing.mattresses.settings.update', $setting) }}" class="modal-content">
                        @csrf
                        @method('PUT')
                        <div class="modal-header">
                            <h2 class="modal-title fs-6 fw-bold" id="setting-title-{{ $setting->id }}">{{ $supply->productName($setting) }} — <bdi>{{ $setting->material->ready_option_label ?: 'مقاس واحد' }}</bdi></h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="supplier-{{ $setting->id }}" class="form-label">المورد المصنّع</label>
                                <select id="supplier-{{ $setting->id }}" name="supplier_id" class="form-select">
                                    <option value="">— غير محدد —</option>
                                    @foreach ($suppliers as $supplier)
                                        <option value="{{ $supplier->id }}" @selected($setting->supplier_id === $supplier->id)>{{ $supplier->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label for="reorder-{{ $setting->id }}" class="form-label">الحد الأدنى للتنبيه</label>
                                    <input id="reorder-{{ $setting->id }}" type="number" min="0" name="reorder_level" class="form-control" value="{{ $setting->reorder_level }}" required>
                                    <div class="form-text">يُنبَّه عندما يصل المتاح لهذا الرقم.</div>
                                </div>
                                <div class="col-6">
                                    <label for="batch-{{ $setting->id }}" class="form-label">أقل كمية إنتاج</label>
                                    <input id="batch-{{ $setting->id }}" type="number" min="1" name="production_batch_size" class="form-control" value="{{ $setting->production_batch_size }}" required>
                                    <div class="form-text">أقل دفعة ينتجها المورد.</div>
                                </div>
                            </div>
                            <div class="border rounded-3 p-2">
                                <label for="balance-{{ $setting->id }}" class="form-label fs-7 fw-semibold">رصيد المورد الفعلي (اختياري)</label>
                                <input id="balance-{{ $setting->id }}" type="number" min="0" step="1" name="supplier_balance" class="form-control" placeholder="الحالي: {{ $qty($row['at_supplier']) }}">
                                <div class="form-text">اكتب الكمية التي أبلغك المورد أنها لديه لك، فيُسجَّل الفرق كتسوية. اتركه فارغاً إن لم يتغير.</div>
                                <input type="text" name="balance_note" class="form-control form-control-sm mt-2" maxlength="300" placeholder="سبب التحديث، مثلاً: جرد مع المورد" aria-label="سبب تحديث الرصيد">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">إلغاء</button>
                            <button type="submit" class="btn btn-primary fw-bold">حفظ</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach

        <section class="card-factory p-3" aria-labelledby="track-heading">
            <h2 id="track-heading" class="h6 fw-bold text-dark mb-1">إضافة مقاسات للمتابعة</h2>
            <p class="fs-7 text-muted mb-2">اختر مقاسات المراتب المورّدة من المنتجات الجاهزة. لإضافة مقاس جديد لمرتبة، أنشئه أولاً كخيار للمنتج في «المواد».</p>
            @if ($untracked->isEmpty())
                <p class="fs-7 text-muted mb-0">كل المنتجات الجاهزة متابَعة.</p>
            @else
                <form method="POST" action="{{ route('purchasing.mattresses.track') }}" x-data="{ filter: '' }">
                    @csrf
                    <input type="search" class="form-control mb-2" placeholder="ابحث باسم المرتبة أو المقاس..." x-model="filter" aria-label="تصفية المنتجات">
                    <div class="track-list">
                        @foreach ($untracked as $material)
                            @php $label = trim(($material->readyParent?->name_ar ?? $material->name_ar).' '.($material->ready_option_label ? '— '.$material->ready_option_label : '')); @endphp
                            <label class="form-check" x-show="!filter || @js($label).includes(filter)">
                                <input class="form-check-input" type="checkbox" name="material_ids[]" value="{{ $material->id }}">
                                <span class="form-check-label fs-7">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-end mt-2">
                        <div>
                            <label for="track-supplier" class="form-label fs-7 mb-1">المورد (اختياري)</label>
                            <select id="track-supplier" name="supplier_id" class="form-select form-select-sm">
                                <option value="">— لاحقاً —</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1" aria-hidden="true"></i> إضافة للمتابعة</button>
                    </div>
                </form>
            @endif
        </section>
    @endif

    @if ($recentMovements->isNotEmpty())
        <section class="table-factory-wrapper" aria-labelledby="movements-heading">
            <div class="pipeline-list-head"><h2 id="movements-heading" class="h6 fw-bold mb-0">آخر حركات رصيد الموردين</h2></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>التاريخ</th><th>المقاس</th><th>المورد</th><th>الحركة</th><th class="text-center">الكمية</th><th>ملاحظة</th></tr></thead>
                    <tbody>
                        @foreach ($recentMovements as $movement)
                            <tr>
                                <td class="text-nowrap fs-8">{{ $movement->created_at->format('Y-m-d H:i') }}</td>
                                <td class="fs-8">{{ $movement->material?->readyParent?->name_ar ?? $movement->material?->name_ar }} <bdi>{{ $movement->material?->ready_option_label }}</bdi></td>
                                <td class="fs-8">{{ $movement->supplier?->name }}</td>
                                <td class="fs-8">{{ \App\Models\SupplierStockMovement::TYPE_LABELS[$movement->movement_type] ?? $movement->movement_type }}</td>
                                <td class="text-center fw-bold {{ (float) $movement->quantity < 0 ? 'text-danger' : 'text-success' }}"><bdi>{{ (float) $movement->quantity > 0 ? '+' : '' }}{{ $qty($movement->quantity) }}</bdi></td>
                                <td class="fs-8 text-muted">{{ $movement->notes }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
@endsection
