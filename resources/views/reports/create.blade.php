@extends('layouts.app')

@section('title', 'الإدخال اليومي — إدارة تكاليف المصنع')

@section('content')
<div class="page-header" style="margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.5rem; margin-bottom: 0.25rem;">الإدخال اليومي لتكاليف الإنتاج</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">أدخل كميات الإنتاج وخيارات التخزين والأقمشة للأصناف المصنعة اليوم</p>
        </div>
        <div>
            <a href="{{ route('reports.index') }}" class="btn btn-secondary">
                عرض السجلات السابقة
            </a>
        </div>
    </div>
</div>

<!-- Duplicate Date Interactive Alert -->
<div id="dateDuplicateAlert" class="alert alert-warning" style="display: {{ $existingReportForToday ? 'flex' : 'none' }}; align-items: center; justify-content: space-between;">
    <div style="display: flex; align-items: center; gap: 0.75rem;">
        <span style="font-size: 1.25rem;">⚠️</span>
        <span id="dateDuplicateMessage">
            تنبيه: يوجد سجل إنتاج محفوظ لهذا التاريخ مسبقاً.
        </span>
    </div>
    <a id="dateDuplicateLink" href="{{ $existingReportForToday ? route('reports.edit', $existingReportForToday) : '#' }}" class="btn btn-sm btn-primary" style="white-space: nowrap;">
        فتح السجل وتعديله
    </a>
</div>

<form id="dailyReportForm" action="{{ route('reports.store') }}" method="POST">
    @csrf

    <!-- Date Card -->
    <div class="card" style="margin-bottom: 1.25rem;">
        <div class="card-body" style="padding: 1.25rem;">
            <div style="max-width: 320px;">
                <label for="production_date" class="form-label">تاريخ الإنتاج <span style="color: var(--danger);">*</span></label>
                <input 
                    type="date" 
                    id="production_date" 
                    name="production_date" 
                    class="form-control @error('production_date') is-invalid @enderror" 
                    value="{{ old('production_date', $today) }}" 
                    required
                >
                @error('production_date')
                    <span class="invalid-feedback">{{ $message }}</span>
                @enderror
                <small style="display: block; color: var(--text-muted); margin-top: 0.25rem; font-size: 0.8rem;">
                    التوقيت المعتمد: توقيت الرياض (Asia/Riyadh)
                </small>
            </div>
        </div>
    </div>

    <!-- Hidden JSON metadata for live calculation preview -->
    <script id="standardsMetadata" type="application/json">
        {!! json_encode($products->mapWithKeys(function($p) {
            $s = $p->costStandard;
            return [$p->code => [
                'wood_without_storage' => (float) ($s->wood_without_storage ?? 0),
                'wood_with_storage' => (float) ($s->wood_with_storage ?? 0),
                'fabric_meters' => (float) ($s->fabric_meters ?? 0),
                'raw_material_transport' => (float) ($s->raw_material_transport ?? 0),
                'foam' => (float) ($s->foam ?? 0),
                'paint' => (float) ($s->paint ?? 0),
                'nails' => (float) ($s->nails ?? 0),
                'hinges' => (float) ($s->hinges ?? 0),
                'packaging' => (float) ($s->packaging ?? 0),
                'carpentry_wages' => (float) ($s->carpentry_wages ?? 0),
                'upholstery_wages' => (float) ($s->upholstery_wages ?? 0),
                'packaging_wages' => (float) ($s->packaging_wages ?? 0),
                'administrative_wages' => (float) ($s->administrative_wages ?? 0),
                'advertising' => (float) ($s->advertising ?? 0),
                'shipping' => (float) ($s->shipping ?? 0),
                'miscellaneous' => (float) ($s->miscellaneous ?? 0),
                'profit_margin' => (float) ($s->profit_margin ?? 0),
            ]];
        })) !!}
    </script>
    <script id="fabricPricesMetadata" type="application/json">
        {!! json_encode($fabricPrices) !!}
    </script>

    <!-- Desktop Table View -->
    <div class="card desktop-table-view">
        <div class="card-header" style="background-color: #f8fafc;">
            <div style="font-weight: 700;">جدول أصناف الإنتاج اليومي (40 صنفاً)</div>
            <div style="font-size: 0.85rem; color: var(--text-muted);">الأصناف غير المنتجة تترك بالكمية 0</div>
        </div>
        <div class="table-responsive" style="max-height: 65vh; overflow-y: auto;">
            <table class="table table-sticky">
                <thead>
                    <tr>
                        <th style="width: 40px; text-align: center;">م</th>
                        <th style="width: 60px; text-align: center;" class="col-sticky-1">الكود</th>
                        <th style="min-width: 200px;" class="col-sticky-2">الصنف والمقاس</th>
                        <th style="width: 100px;">العدد</th>
                        <th style="width: 130px;">نوع التخزين</th>
                        <th style="width: 150px;">شركة القماش</th>
                        <th style="width: 130px;">نوع القماش</th>
                        <th style="width: 130px; text-align: left;">التكلفة التقديرية</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $index => $product)
                        @php
                            $oldQty = old("items.{$index}.quantity", 0);
                            $oldStorage = old("items.{$index}.storage_type", 'بدون تخزين');
                            $oldCompany = old("items.{$index}.fabric_company_id");
                            $oldType = old("items.{$index}.fabric_type_id");
                        @endphp
                        <tr id="row-{{ $index }}" data-index="{{ $index }}" data-product-id="{{ $product->id }}" data-code="{{ $product->code }}" class="{{ $oldQty > 0 ? 'row-active' : '' }}">
                            <td style="text-align: center; color: var(--text-muted); font-weight: 600;">
                                {{ $product->order }}
                                <input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $product->id }}">
                            </td>
                            <td style="text-align: center; font-weight: 800; color: var(--primary);" class="col-sticky-1">
                                {{ $product->code }}
                            </td>
                            <td style="font-weight: 600; white-space: nowrap;" class="col-sticky-2">
                                {{ $product->full_name }}
                            </td>
                            <td>
                                <input 
                                    type="number" 
                                    name="items[{{ $index }}][quantity]" 
                                    class="form-control input-qty" 
                                    data-index="{{ $index }}"
                                    data-field="quantity"
                                    value="{{ $oldQty }}" 
                                    min="0" 
                                    step="1"
                                    style="text-align: center; font-weight: 700;"
                                >
                            </td>
                            <td>
                                <select 
                                    name="items[{{ $index }}][storage_type]" 
                                    class="form-select select-storage" 
                                    data-index="{{ $index }}"
                                    data-field="storage_type"
                                >
                                    <option value="بدون تخزين" {{ $oldStorage == 'بدون تخزين' ? 'selected' : '' }}>بدون تخزين</option>
                                    <option value="بتخزين" {{ $oldStorage == 'بتخزين' ? 'selected' : '' }}>بتخزين</option>
                                </select>
                            </td>
                            <td>
                                <select 
                                    name="items[{{ $index }}][fabric_company_id]" 
                                    class="form-select select-company" 
                                    data-index="{{ $index }}"
                                    data-field="fabric_company_id"
                                >
                                    <option value="">-- شركة القماش --</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ $oldCompany == $company->id ? 'selected' : '' }}>
                                            {{ $company->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select 
                                    name="items[{{ $index }}][fabric_type_id]" 
                                    class="form-select select-type" 
                                    data-index="{{ $index }}"
                                    data-field="fabric_type_id"
                                >
                                    <option value="">-- نوع القماش --</option>
                                    @foreach($types as $type)
                                        <option value="{{ $type->id }}" {{ $oldType == $type->id ? 'selected' : '' }}>
                                            {{ $type->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td style="text-align: left; font-weight: 700; color: var(--text-heading);" class="cell-line-total">
                                0.00 ر.س
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Mobile Cards View -->
    <div class="mobile-cards-view">
        <div style="font-weight: 700; margin-bottom: 0.75rem;">قائمة الأصناف (40 صنفاً)</div>
        @foreach($products as $index => $product)
            @php
                $oldQty = old("items.{$index}.quantity", 0);
                $oldStorage = old("items.{$index}.storage_type", 'بدون تخزين');
                $oldCompany = old("items.{$index}.fabric_company_id");
                $oldType = old("items.{$index}.fabric_type_id");
            @endphp
            <div id="card-{{ $index }}" class="prod-card {{ $oldQty > 0 ? 'has-qty' : '' }}" data-index="{{ $index }}">
                <div class="prod-card-header">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span class="badge badge-primary">{{ $product->code }}</span>
                        <span style="font-weight: 700; font-size: 0.95rem;">{{ $product->full_name }}</span>
                    </div>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">#{{ $product->order }}</span>
                </div>
                <div class="prod-card-body">
                    <div class="prod-card-grid">
                        <div>
                            <label class="form-label" style="font-size: 0.8rem;">العدد المنتَج</label>
                            <input 
                                type="number" 
                                class="form-control input-qty" 
                                data-index="{{ $index }}"
                                data-field="quantity"
                                value="{{ $oldQty }}" 
                                min="0" 
                                step="1"
                                style="text-align: center; font-weight: 700;"
                            >
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.8rem;">نوع التخزين</label>
                            <select 
                                class="form-select select-storage" 
                                data-index="{{ $index }}"
                                data-field="storage_type"
                            >
                                <option value="بدون تخزين" {{ $oldStorage == 'بدون تخزين' ? 'selected' : '' }}>بدون تخزين</option>
                                <option value="بتخزين" {{ $oldStorage == 'بتخزين' ? 'selected' : '' }}>بتخزين</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.8rem;">شركة القماش</label>
                            <select 
                                class="form-select select-company" 
                                data-index="{{ $index }}"
                                data-field="fabric_company_id"
                            >
                                <option value="">-- اختياري --</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" {{ $oldCompany == $company->id ? 'selected' : '' }}>
                                        {{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.8rem;">نوع القماش</label>
                            <select 
                                class="form-select select-type" 
                                data-index="{{ $index }}"
                                data-field="fabric_type_id"
                            >
                                <option value="">-- اختياري --</option>
                                @foreach($types as $type)
                                    <option value="{{ $type->id }}" {{ $oldType == $type->id ? 'selected' : '' }}>
                                        {{ $type->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Notes Card -->
    <div class="card" style="margin-top: 1.5rem; margin-bottom: 5rem;">
        <div class="card-body">
            <label for="notes" class="form-label">ملاحظات اليوم (اختياري)</label>
            <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="أي ملاحظات خاصة بإنتاج هذا اليوم...">{{ old('notes') }}</textarea>
        </div>
    </div>

    <!-- Fixed Bottom Summary & Action Bar -->
    <div class="summary-bar no-print">
        <div class="container" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div class="summary-stats">
                <div class="summary-item">
                    <span class="summary-label">إجمالي القطع</span>
                    <span id="summaryTotalQty" class="summary-val">0</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">أصناف منتَجة</span>
                    <span id="summaryActiveCount" class="summary-val">0</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">إجمالي التكلفة التقديرية</span>
                    <span id="summaryTotalCost" class="summary-val highlight">0.00 ر.س</span>
                </div>
            </div>

            <div class="summary-actions" style="display: flex; gap: 0.75rem;">
                <a href="{{ route('reports.index') }}" class="btn btn-secondary">
                    إلغاء
                </a>
                <button type="submit" id="submitBtn" class="btn btn-primary" style="padding-left: 2rem; padding-right: 2rem;">
                    حفظ البيانات
                </button>
            </div>
        </div>
    </div>
</form>
@endsection
