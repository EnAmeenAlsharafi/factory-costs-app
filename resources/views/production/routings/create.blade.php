@extends('layouts.app')

@section('title', 'إنشاء مسار تصنيع - مصنع مفروشات سدير')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="fas fa-plus-circle text-primary me-2"></i>إنشاء مسار تصنيع جديد</h4>
            <p class="text-muted mb-0">تعريف تسلسل المراحل التشغيلية والفروع المتوازية والاعتماديات بين الأقسام</p>
        </div>
        <a href="{{ route('production.routings.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-right me-1"></i> عودة للمسارات
        </a>
    </div>

    <form action="{{ route('production.routings.store') }}" method="POST" id="routing-form">
        @csrf

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-info-circle me-1"></i> البيانات الأساسية للمسار</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label required">اسم مسار التصنيع</label>
                        <input type="text" name="name_ar" class="form-control @error('name_ar') is-invalid @enderror" value="{{ old('name_ar') }}" placeholder="مثال: مسار تصنيع الأسرة القياسي (فرع الظهر + البوكسات)" required>
                        @error('name_ar') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">الوصف والملاحظات</label>
                        <input type="text" name="description" class="form-control" value="{{ old('description') }}" placeholder="وصف مقتضب للمسار والمنتجات المستهدفة">
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-tasks me-1"></i> عمليات المسار التشغيلية (Routing Operations)</h6>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-op-btn">
                    <i class="fas fa-plus me-1"></i> إضافة عملية
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0" id="operations-table">
                        <thead class="bg-light">
                            <tr>
                                <th style="width: 70px;">التسلسل</th>
                                <th style="width: 200px;">مركز العمل / القسم</th>
                                <th style="width: 150px;">كود العملية</th>
                                <th>اسم العملية</th>
                                <th style="width: 180px;">الفرع التشغيلي (Branch)</th>
                                <th style="width: 70px;" class="text-center">حذف</th>
                            </tr>
                        </thead>
                        <tbody id="operations-tbody">
                            <!-- Default Bed Routing Template Pre-filled -->
                            @php
                                $defaultOps = [
                                    ['seq' => 10, 'wc_code' => 'WC_CARPENTRY', 'code' => 'OP_CARPENTRY', 'name' => 'قص وتأطير هيكل الخشب', 'branch' => 'BRANCH_A'],
                                    ['seq' => 20, 'wc_code' => 'WC_FOAM', 'code' => 'OP_FOAM', 'name' => 'قص وتلبيس الإسفنج', 'branch' => 'BRANCH_A'],
                                    ['seq' => 30, 'wc_code' => 'WC_UPHOLSTERY', 'code' => 'OP_UPHOLSTERY', 'name' => 'خياطة وتنجيد القماش', 'branch' => 'BRANCH_A'],
                                    ['seq' => 15, 'wc_code' => 'WC_BOX_PROD', 'code' => 'OP_BOX_PROD', 'name' => 'تصنيع هيكل البوكس', 'branch' => 'BRANCH_B'],
                                    ['seq' => 25, 'wc_code' => 'WC_BOX_PREP', 'code' => 'OP_BOX_PREP', 'name' => 'تلبيس وتجهيز البوكسات', 'branch' => 'BRANCH_B'],
                                    ['seq' => 40, 'wc_code' => 'WC_ASSEMBLY', 'code' => 'OP_ASSEMBLY', 'name' => 'تجميع الظهر والقواعد والسحارات', 'branch' => 'MAIN'],
                                    ['seq' => 50, 'wc_code' => 'WC_PACKAGING', 'code' => 'OP_PACKAGING', 'name' => 'الفحص الجودة النهائي والتغليف', 'branch' => 'MAIN'],
                                ];
                            @endphp

                            @foreach($defaultOps as $idx => $def)
                                <tr>
                                    <td>
                                        <input type="number" name="operations[{{ $idx }}][sequence_number]" class="form-control form-control-sm text-center" value="{{ $def['seq'] }}" required>
                                    </td>
                                    <td>
                                        <select name="operations[{{ $idx }}][work_center_id]" class="form-select form-select-sm" required>
                                            @foreach($workCenters as $wc)
                                                <option value="{{ $wc->id }}" {{ $wc->code === $def['wc_code'] ? 'selected' : '' }}>
                                                    {{ $wc->name_ar }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" name="operations[{{ $idx }}][operation_code]" class="form-control form-control-sm font-monospace" value="{{ $def['code'] }}" required>
                                    </td>
                                    <td>
                                        <input type="text" name="operations[{{ $idx }}][name_ar]" class="form-control form-control-sm" value="{{ $def['name'] }}" required>
                                    </td>
                                    <td>
                                        <select name="operations[{{ $idx }}][branch_key]" class="form-select form-select-sm">
                                            <option value="MAIN" {{ $def['branch'] === 'MAIN' ? 'selected' : '' }}>الفرع الرئيسي (MAIN)</option>
                                            <option value="BRANCH_A" {{ $def['branch'] === 'BRANCH_A' ? 'selected' : '' }}>فرع أ (الظهر والجانبيات)</option>
                                            <option value="BRANCH_B" {{ $def['branch'] === 'BRANCH_B' ? 'selected' : '' }}>فرع ب (البوكسات)</option>
                                        </select>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-op-btn"><i class="fas fa-times"></i></button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('production.routings.index') }}" class="btn btn-light">إلغاء</a>
            <button type="submit" class="btn btn-primary px-4"><i class="fas fa-save me-1"></i> حفظ مسار التصنيع</button>
        </div>
    </form>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    let tbody = document.getElementById('operations-tbody');
    let addBtn = document.getElementById('add-op-btn');
    let opIndex = tbody.children.length;

    addBtn.addEventListener('click', function() {
        let tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="number" name="operations[${opIndex}][sequence_number]" class="form-control form-control-sm text-center" value="${(opIndex + 1) * 10}" required></td>
            <td>
                <select name="operations[${opIndex}][work_center_id]" class="form-select form-select-sm" required>
                    @foreach($workCenters as $wc)
                        <option value="{{ $wc->id }}">{{ $wc->name_ar }}</option>
                    @endforeach
                </select>
            </td>
            <td><input type="text" name="operations[${opIndex}][operation_code]" class="form-control form-control-sm font-monospace" value="OP_${opIndex+1}" required></td>
            <td><input type="text" name="operations[${opIndex}][name_ar]" class="form-control form-control-sm" placeholder="اسم العملية" required></td>
            <td>
                <select name="operations[${opIndex}][branch_key]" class="form-select form-select-sm">
                    <option value="MAIN">الفرع الرئيسي (MAIN)</option>
                    <option value="BRANCH_A">فرع أ (الظهر والجانبيات)</option>
                    <option value="BRANCH_B">فرع ب (البوكسات)</option>
                </select>
            </td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-op-btn"><i class="fas fa-times"></i></button></td>
        `;
        tbody.appendChild(tr);
        opIndex++;
    });

    tbody.addEventListener('click', function(e) {
        if (e.target.closest('.remove-op-btn')) {
            if (tbody.children.length > 1) {
                e.target.closest('tr').remove();
            }
        }
    });
});
</script>
@endpush
@endsection
