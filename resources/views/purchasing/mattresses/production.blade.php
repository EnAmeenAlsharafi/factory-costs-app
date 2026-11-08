@extends('layouts.app')

@section('title', 'طلبات إنتاج المراتب - مصنع مفروشات سدير')
@section('page-title', 'المراتب')

@section('content')
@php
    $supply = app(\App\Services\ReadyProductSupplyService::class);
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    $bySupplier = $rows->groupBy(fn ($row) => $row['setting']->supplier_id);
    $createdId = session('created_production_request');
@endphp

<div class="d-flex flex-column gap-3">
    @include('purchasing.mattresses.partials.tabs', ['subtitle' => 'اطلب من المورد إنتاج دفعة جديدة. عند تأكيد الإنتاج تُضاف الكمية لرصيدنا لدى المورد، ويورّدها لنا عند الطلب.'])

    @if ($createdId)
        <div class="card-factory pipeline-result is-success" role="status">
            <p class="fw-bold mb-2"><i class="fas fa-circle-check text-success me-1" aria-hidden="true"></i> أُنشئ طلب الإنتاج. نزّل الملف وأرسله للمورد:</p>
            <a href="{{ route('purchasing.mattresses.production.pdf', $createdId) }}" class="btn btn-primary btn-sm"><i class="fas fa-file-pdf me-1" aria-hidden="true"></i> تنزيل طلب الإنتاج PDF</a>
        </div>
    @endif

    @if ($rows->isEmpty())
        <div class="card-factory empty-state" role="status">
            <i class="fas fa-industry fs-2 mb-3 d-block opacity-50" aria-hidden="true"></i>
            <p class="fw-semibold mb-1">لا توجد مقاسات متابَعة لها مورد.</p>
            <p class="fs-7 mb-0">أضف المقاسات وحدد مورد كل مقاس من <a href="{{ route('purchasing.mattresses.stock') }}">المخزون والتنبيهات</a>.</p>
        </div>
    @endif

    @foreach ($bySupplier as $supplierId => $supplierRows)
        @php $supplier = $supplierRows->first()['setting']->supplier; @endphp
        <form method="POST" action="{{ route('purchasing.mattresses.production.store') }}" class="table-factory-wrapper"
              x-data="{ count: {{ $supplierRows->where('is_low', true)->count() }}, busy: false }"
              @change="count = $el.querySelectorAll('input.pick:checked').length" @submit="busy = true">
            @csrf
            <input type="hidden" name="supplier_id" value="{{ $supplierId }}">
            <div class="pipeline-list-head">
                <h2 class="h6 fw-bold mb-0"><i class="fas fa-truck text-primary me-1" aria-hidden="true"></i> {{ $supplier->name }}</h2>
                @if ($supplierRows->where('is_low', true)->isNotEmpty())
                    <span class="status-chip status-chip-danger">{{ $supplierRows->where('is_low', true)->count() }} مقاس عند الحد الأدنى</span>
                @endif
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 2.5rem;"><span class="visually-hidden">تحديد</span></th>
                            <th>المرتبة / المقاس</th>
                            <th class="text-center">المتاح</th>
                            <th class="text-center">الحد الأدنى</th>
                            <th class="text-center">أقل إنتاج</th>
                            <th style="width: 9rem;">كمية الإنتاج</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($supplierRows as $row)
                            @php $setting = $row['setting']; $suggested = $row['is_low'] ? $row['suggested'] : $setting->production_batch_size; @endphp
                            <tr @class(['table-warning' => $row['is_low']])>
                                <td><input type="checkbox" class="form-check-input pipeline-check pick" name="items[{{ $setting->material_id }}][selected]" value="1" @checked($row['is_low']) aria-label="تحديد {{ $setting->material->ready_option_label }}"></td>
                                <td><span class="fw-semibold">{{ $supply->productName($setting) }}</span> <span class="status-chip status-chip-secondary"><bdi>{{ $setting->material->ready_option_label ?: 'مقاس واحد' }}</bdi></span></td>
                                <td class="text-center fw-bold {{ $row['is_low'] ? 'text-danger' : '' }}">{{ $qty($row['available']) }}</td>
                                <td class="text-center">{{ $setting->reorder_level }}</td>
                                <td class="text-center">{{ $setting->production_batch_size }}</td>
                                <td>
                                    <label class="visually-hidden" for="prod-{{ $setting->material_id }}">كمية الإنتاج</label>
                                    <input id="prod-{{ $setting->material_id }}" type="number" min="{{ $setting->production_batch_size }}" step="1" name="items[{{ $setting->material_id }}][quantity]" class="form-control form-control-sm text-center" value="{{ $suggested }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pipeline-bar">
                <span class="pipeline-bar-count"><strong x-text="count">0</strong> مقاس محدد</span>
                <label class="visually-hidden" for="notes-{{ $supplierId }}">ملاحظات</label>
                <input id="notes-{{ $supplierId }}" type="text" name="notes" class="form-control pipeline-bar-field" maxlength="1000" placeholder="ملاحظات للمورد (اختياري)">
                <button type="submit" class="btn btn-primary pipeline-bar-submit" :disabled="count === 0 || busy"><i class="fas fa-industry" aria-hidden="true"></i> إنشاء طلب إنتاج</button>
            </div>
        </form>
    @endforeach

    <section class="table-factory-wrapper" aria-labelledby="requests-heading">
        <div class="pipeline-list-head"><h2 id="requests-heading" class="h6 fw-bold mb-0">طلبات الإنتاج</h2></div>
        @if ($requests->isEmpty())
            <p class="text-muted fs-7 p-3 mb-0">لم تُنشأ طلبات إنتاج بعد.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>رقم الطلب</th><th>المورد</th><th>التاريخ</th><th>المقاسات</th><th>الحالة</th><th class="text-end">إجراء</th></tr></thead>
                    <tbody>
                        @foreach ($requests as $productionRequest)
                            @php $status = \App\Models\SupplierProductionRequest::STATUS_LABELS[$productionRequest->status] ?? [$productionRequest->status, 'secondary']; @endphp
                            <tr>
                                <td class="text-nowrap fw-bold"><bdi>{{ $productionRequest->request_number }}</bdi></td>
                                <td>{{ $productionRequest->supplier?->name }}</td>
                                <td class="text-nowrap">{{ $productionRequest->request_date?->format('Y-m-d') }}</td>
                                <td class="fs-8">
                                    @foreach ($productionRequest->lines as $line)
                                        <div><bdi>{{ $line->material?->ready_option_label ?: $line->material?->name_ar }}</bdi>: {{ $line->produced_quantity ?? $line->quantity }}@if ($line->produced_quantity !== null && $line->produced_quantity !== $line->quantity) <span class="text-muted">(طُلب {{ $line->quantity }})</span>@endif</div>
                                    @endforeach
                                </td>
                                <td><span class="status-chip status-chip-{{ $status[1] }}">{{ $status[0] }}</span></td>
                                <td class="text-end">
                                    <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                        <a href="{{ route('purchasing.mattresses.production.pdf', $productionRequest) }}" class="btn btn-sm btn-outline-primary text-nowrap"><i class="fas fa-file-pdf" aria-hidden="true"></i> PDF</a>
                                        @if ($productionRequest->status === 'REQUESTED')
                                            <button type="button" class="btn btn-sm btn-success text-nowrap" data-bs-toggle="modal" data-bs-target="#confirm-{{ $productionRequest->id }}"><i class="fas fa-check" aria-hidden="true"></i> تم الإنتاج</button>
                                            <form method="POST" action="{{ route('purchasing.mattresses.production.cancel', $productionRequest) }}" data-confirm="إلغاء طلب الإنتاج {{ $productionRequest->request_number }}؟">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger">إلغاء</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @foreach ($requests->where('status', 'REQUESTED') as $productionRequest)
        <div class="modal fade" id="confirm-{{ $productionRequest->id }}" tabindex="-1" aria-labelledby="confirm-title-{{ $productionRequest->id }}" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('purchasing.mattresses.production.confirm', $productionRequest) }}" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title fs-6 fw-bold" id="confirm-title-{{ $productionRequest->id }}">تأكيد إنتاج <bdi>{{ $productionRequest->request_number }}</bdi></h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                    </div>
                    <div class="modal-body">
                        <p class="fs-7 text-muted">أدخل الكمية التي أنتجها المورد فعلاً لكل مقاس. تُضاف لرصيدنا لدى المورد.</p>
                        @foreach ($productionRequest->lines as $line)
                            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                <label for="produced-{{ $line->id }}" class="fs-7 mb-0">{{ $line->material?->readyParent?->name_ar ?? $line->material?->name_ar }} <bdi class="fw-bold">{{ $line->material?->ready_option_label }}</bdi> <span class="text-muted">(طُلب {{ $line->quantity }})</span></label>
                                <input id="produced-{{ $line->id }}" type="number" min="0" name="produced[{{ $line->id }}]" value="{{ $line->quantity }}" class="form-control form-control-sm text-center" style="max-width: 6rem;">
                            </div>
                        @endforeach
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-success fw-bold">تأكيد الإنتاج</button>
                    </div>
                </form>
            </div>
        </div>
    @endforeach
</div>
@endsection
