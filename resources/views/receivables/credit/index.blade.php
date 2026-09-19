@extends('layouts.app')

@section('title', 'إدارة الائتمان والحدود الائتمانية')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="fas fa-credit-card text-warning me-2"></i> إدارة الائتمان والحدود الائتمانية (Customer Credit Profiles)
            </h1>
            <p class="text-muted mb-0 small">تحديد السقف الائتماني لعملاء الجملة والشركات ومتابعة التعرض المالي.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 py-3">العميل</th>
                            <th>حالة الائتمان</th>
                            <th class="text-end">حد الائتمان المعتمد</th>
                            <th class="text-end">التعرض الحالي (المستحق)</th>
                            <th class="text-end">الائتمان المتاح</th>
                            <th class="text-center">أجَل السداد (يوم)</th>
                            <th class="text-center">إيقاف عند التجاوز</th>
                            <th class="text-end pe-3">إجراءات والتعديل</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($customers as $cust)
                            @php
                                $profile = $cust->creditProfile;
                                $enabled = $profile ? $profile->credit_enabled : $cust->is_credit_customer;
                                $limit = (float) ($profile ? $profile->credit_limit : $cust->credit_limit);
                                $exposure = (float) $cust->current_credit_exposure;
                                $available = max(0.00, $limit - $exposure);
                                $percent = $limit > 0 ? round(($exposure / $limit) * 100, 1) : 0;
                            @endphp
                            <tr>
                                <td class="ps-3 py-3">
                                    <div class="fw-bold text-dark">{{ $cust->name }}</div>
                                    <div class="small text-muted font-monospace">{{ $cust->customer_code }}</div>
                                </td>
                                <td>
                                    @if (! $enabled)
                                        <span class="badge bg-secondary px-2 py-1">غير مفعّل</span>
                                    @elseif ($exposure > $limit && $limit > 0)
                                        <span class="badge bg-danger px-2 py-1">تجاوز الحد ({{ $percent }}%)</span>
                                    @elseif ($percent >= ($profile->warning_threshold_percent ?? 80) && $limit > 0)
                                        <span class="badge bg-warning text-dark px-2 py-1">تنبيه ({{ $percent }}%)</span>
                                    @else
                                        <span class="badge bg-success px-2 py-1">طبيعي ({{ $percent }}%)</span>
                                    @endif
                                </td>
                                <td class="text-end fw-bold text-dark">{{ number_format($limit, 2) }} ر.س</td>
                                <td class="text-end fw-bold text-danger">{{ number_format($exposure, 2) }} ر.س</td>
                                <td class="text-end fw-bold text-success">{{ number_format($available, 2) }} ر.س</td>
                                <td class="text-center">{{ $profile->credit_days ?? 30 }} يوم</td>
                                <td class="text-center">
                                    @if ($profile && $profile->hold_when_exceeded)
                                        <span class="badge bg-danger bg-opacity-10 text-danger border px-2 py-1">نعم (إيقاف تلقائي)</span>
                                    @else
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border px-2 py-1">لا</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    @can('receivables.credit.manage')
                                        <button type="button" class="btn btn-sm btn-outline-dark fw-bold" data-bs-toggle="modal" data-bs-target="#editCreditModal{{ $cust->id }}">
                                            <i class="fas fa-edit me-1"></i> تعديل الحد
                                        </button>
                                    @endcan
                                </td>
                            </tr>

                            {{-- Edit Credit Profile Modal --}}
                            @can('receivables.credit.manage')
                                <div class="modal fade" id="editCreditModal{{ $cust->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST" action="{{ route('receivables.customers.credit.update', $cust) }}">
                                                @csrf
                                                @method('PUT')
                                                <div class="modal-header bg-light">
                                                    <h5 class="modal-title fw-bold">تعديل ملف الائتمان - {{ $cust->name }}</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">تفعيل خيار البيع الآجل (الائتمان)</label>
                                                        <select name="credit_enabled" class="form-select">
                                                            <option value="1" {{ $enabled ? 'selected' : '' }}>مفعّل (عميل آجل)</option>
                                                            <option value="0" {{ ! $enabled ? 'selected' : '' }}>غير مفعّل (نقدي / عربون فقط)</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">الحد الائتماني الأقصى المعتمد (ر.س)</label>
                                                        <input type="number" step="0.01" min="0" name="credit_limit" class="form-control font-monospace fw-bold" value="{{ old('credit_limit', $limit) }}" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">أجل السداد الافتراضي (بالأيام)</label>
                                                        <input type="number" min="0" name="credit_days" class="form-control" value="{{ old('credit_days', $profile->credit_days ?? 30) }}" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">نسبة التنبيه قبل الوصول للحد (%)</label>
                                                        <input type="number" step="0.1" min="1" max="100" name="warning_threshold_percent" class="form-control" value="{{ old('warning_threshold_percent', $profile->warning_threshold_percent ?? 80) }}" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input ms-2" type="checkbox" name="hold_when_exceeded" value="1" id="holdSwitch{{ $cust->id }}" {{ ($profile ? $profile->hold_when_exceeded : true) ? 'checked' : '' }}>
                                                            <label class="form-check-label fw-bold small" for="holdSwitch{{ $cust->id }}">
                                                                إيقاف اعتماد الإنتاج والتسليم تلقائياً عند تجاوز الحد الائتماني
                                                            </label>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-bold small">ملاحظات واعتمادات الائتمان</label>
                                                        <textarea name="notes" rows="2" class="form-control" placeholder="مبررات منح أو تعديل الحد الائتماني...">{{ old('notes', $profile->notes ?? '') }}</textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                                                    <button type="submit" class="btn btn-warning fw-bold">حفظ التحديثات</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endcan
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">لا يوجد عملاء في النظام.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($customers->hasPages())
            <div class="card-footer bg-white py-3">
                {{ $customers->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
