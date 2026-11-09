@extends('layouts.app')

@section('title', 'إنشاء طلب خامات إنتاج - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <!-- Header -->
    <div class="page-header-card d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">طلب خامات إنتاج جديد</h1>
            <p class="page-header-subtitle text-muted mb-0 fs-7">أمر إنتاج: <span class="font-monospace fw-bold text-primary">{{ $productionOrder->production_order_number }}</span> - {{ $productionOrder->productModel?->name_ar ?? $productionOrder->custom_design_name }}</p>
        </div>
        <a href="{{ route('production.orders.show', $productionOrder) }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i> عودة لأمر الإنتاج
        </a>
    </div>

    @php
        $oldManualLines = collect(old('lines', []))
            ->filter(fn ($line, $key) => str_starts_with((string) $key, 'm'))
            ->map(fn ($line, $key) => [
                'key' => $key,
                'material_id' => isset($line['material_id']) && $line['material_id'] !== '' ? (int) $line['material_id'] : '',
                'fabric_color_id' => $line['fabric_color_id'] ?? '',
                'requested_quantity' => $line['requested_quantity'] ?? '',
                'request_reason' => $line['request_reason'] ?? 'PLANNED_PRODUCTION',
            ])->values();
        $startWithEmptyLine = $oldManualLines->isEmpty() && $productionOrder->materialRequirements->isEmpty();
        $defaultManualReason = $productionOrder->materialRequirements->isEmpty() ? 'PLANNED_PRODUCTION' : 'ADDITIONAL_REQUIREMENT';
    @endphp
    <form action="{{ route('production.material-requests.store') }}" method="POST"
          x-data="{
              materials: @js($materialOptions),
              manualLines: @js($oldManualLines),
              nextKey: {{ (int) $oldManualLines->map(fn ($line) => (int) substr($line['key'], 1))->max() + 1 }},
              materialOf(line) { return this.materials.find(m => m.id === Number(line.material_id)); },
              addLine() { this.manualLines.push({ key: 'm' + this.nextKey++, material_id: '', fabric_color_id: '', requested_quantity: '', request_reason: '{{ $defaultManualReason }}' }); },
              removeLine(key) { this.manualLines = this.manualLines.filter(l => l.key !== key); },
          }"
          @if($startWithEmptyLine) x-init="addLine()" @endif>
        @csrf
        <input type="hidden" name="production_order_id" value="{{ $productionOrder->id }}">

        <div class="row g-4">
            <!-- Header Data -->
            <div class="col-md-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-info-circle me-1"></i> بيانات الطلب والمستودع</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label required">المستودع الموجه له الطلب</label>
                            <select name="warehouse_id" class="form-select" required>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" @selected(old('warehouse_id') == $wh->id)>{{ $wh->name_ar }}@if($wh->type) ({{ $wh->type }})@endif</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">القسم الطالب</label>
                            <select name="requested_from_department_id" class="form-select">
                                <option value="">تحديد القسم الطالب...</option>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}" {{ auth()->user()->department_id == $dept->id ? 'selected' : '' }}>{{ $dept->name_ar }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label required">تاريخ الطلب</label>
                            <input type="date" name="request_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ملاحظات الطلب</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="أية ملاحظات إضافية لأمين المستودع..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lines Table -->
            <div class="col-md-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-boxes-stacked me-1"></i> بنود المواد المطلوبة</h6>
                        <span class="badge bg-light text-dark border">مقتبسة من احتياج BOM / طلب إضافي</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="lines-table">
                                <thead class="bg-light">
                                    <tr>
                                        <th style="min-width: 14rem;">المادة الخام</th>
                                        <th>لون القماش</th>
                                        <th>الكمية المطلوبة</th>
                                        <th>سبب الطلب</th>
                                        <th>الوحدة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($productionOrder->materialRequirements as $index => $req)
                                        <tr>
                                            <td>
                                                <input type="hidden" name="lines[{{ $index }}][production_material_requirement_id]" value="{{ $req->id }}">
                                                <input type="hidden" name="lines[{{ $index }}][material_id]" value="{{ $req->material_id }}">
                                                <input type="hidden" name="lines[{{ $index }}][base_unit_id]" value="{{ $req->unit_id }}">
                                                <div class="fw-bold">{{ $req->material_name_snapshot }}</div>
                                                <small class="text-muted">مخطط BOM: {{ (float)$req->total_planned_quantity }} {{ $req->unit?->name_ar }}</small>
                                            </td>
                                            <td>
                                                @if($req->material?->category?->code === 'FABRIC')
                                                    <select name="lines[{{ $index }}][fabric_color_id]" class="form-select form-select-sm">
                                                        <option value="">تحديد اللون...</option>
                                                        @foreach($fabricColors as $fc)
                                                            <option value="{{ $fc->id }}" {{ $productionOrder->fabric_color_id == $fc->id ? 'selected' : '' }}>{{ $fc->color_name_ar }}</option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>
                                                <input type="number" step="0.0001" name="lines[{{ $index }}][requested_quantity]" class="form-control form-control-sm" value="{{ (float)$req->total_planned_quantity }}" required min="0.0001">
                                            </td>
                                            <td>
                                                <select name="lines[{{ $index }}][request_reason]" class="form-select form-select-sm" style="min-width: 9rem;">
                                                    <option value="PLANNED_PRODUCTION" selected>إنتاج مخطط</option>
                                                    <option value="ADDITIONAL_REQUIREMENT">احتياج إضافي</option>
                                                    <option value="REWORK">إعادة تصنيع</option>
                                                    <option value="REMANUFACTURE">إعادة إنتاج</option>
                                                    <option value="CORRECTION">تصحيح</option>
                                                </select>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border">{{ $req->unit?->name_ar }}</span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr x-show="manualLines.length === 0">
                                            <td colspan="5" class="text-center py-4 text-muted">
                                                لا توجد وصفة تصنيع معتمدة لهذا الأمر، لذلك لم تُحسب الخامات تلقائياً. أضف الخامات المطلوبة يدوياً.
                                            </td>
                                        </tr>
                                    @endforelse
                                    <template x-for="line in manualLines" :key="line.key">
                                        <tr>
                                            <td data-label="المادة الخام">
                                                <select class="form-select form-select-sm" :name="'lines[' + line.key + '][material_id]'" x-model.number="line.material_id" @change="line.fabric_color_id = ''" required aria-label="المادة الخام">
                                                    <option value="">اختر الخامة...</option>
                                                    <template x-for="material in materials" :key="material.id">
                                                        <option :value="material.id" x-text="material.label" :selected="material.id === line.material_id"></option>
                                                    </template>
                                                </select>
                                                <input type="hidden" :name="'lines[' + line.key + '][base_unit_id]'" :value="materialOf(line)?.unit_id ?? ''">
                                            </td>
                                            <td data-label="لون القماش">
                                                <template x-if="materialOf(line)?.colors.length">
                                                    <select class="form-select form-select-sm" :name="'lines[' + line.key + '][fabric_color_id]'" x-model="line.fabric_color_id" aria-label="لون القماش">
                                                        <option value="">تحديد اللون...</option>
                                                        <template x-for="color in materialOf(line).colors" :key="color.id">
                                                            <option :value="color.id" x-text="color.name"></option>
                                                        </template>
                                                    </select>
                                                </template>
                                                <span class="text-muted" x-show="!materialOf(line)?.colors.length">—</span>
                                            </td>
                                            <td data-label="الكمية المطلوبة">
                                                <input type="number" step="0.0001" min="0.0001" class="form-control form-control-sm" :name="'lines[' + line.key + '][requested_quantity]'" x-model="line.requested_quantity" required aria-label="الكمية المطلوبة">
                                            </td>
                                            <td data-label="سبب الطلب">
                                                <select class="form-select form-select-sm" style="min-width: 9rem;" :name="'lines[' + line.key + '][request_reason]'" x-model="line.request_reason" aria-label="سبب الطلب">
                                                    <option value="PLANNED_PRODUCTION">إنتاج مخطط</option>
                                                    <option value="ADDITIONAL_REQUIREMENT">احتياج إضافي</option>
                                                    <option value="REWORK">إعادة تصنيع</option>
                                                    <option value="REMANUFACTURE">إعادة إنتاج</option>
                                                    <option value="CORRECTION">تصحيح</option>
                                                </select>
                                            </td>
                                            <td data-label="الوحدة">
                                                <div class="d-flex align-items-center gap-2 justify-content-between">
                                                    <span class="badge bg-light text-dark border" x-text="materialOf(line)?.unit_name ?? '—'"></span>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" @click="removeLine(line.key)" aria-label="حذف السطر">
                                                        <i class="fas fa-trash" aria-hidden="true"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 border-top">
                            <button type="button" class="btn btn-outline-primary btn-sm" @click="addLine()">
                                <i class="fas fa-plus me-1" aria-hidden="true"></i> إضافة خامة
                            </button>
                        </div>
                    </div>
                    <div class="card-footer bg-white text-end py-3">
                        <button type="submit" name="action" value="draft" class="btn btn-outline-primary me-2">
                            <i class="fas fa-save me-1"></i> حفظ كمسودة
                        </button>
                        <button type="submit" name="action" value="submit" class="btn btn-success">
                            <i class="fas fa-paper-plane me-1"></i> تقديم للمستودع فوراً
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
