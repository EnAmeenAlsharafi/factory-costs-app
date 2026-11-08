@extends('layouts.app')

@section('title', 'طلبات القماش من الموردين - مصنع مفروشات سدير')
@section('page-title', 'طلبات القماش')

@section('content')
@php
    $orderableRows = $rows->where('orderable', true)->values();
    $unlinkedRows = $rows->where('orderable', false)->values();
    $items = $orderableRows->map(function (array $row) {
        $order = $row['order'];
        $spec = $order->fabricMaterial->fabricSpec;

        return [
            'id' => $order->id,
            'number' => $order->production_order_number,
            'product' => $order->display_name,
            'quantity' => (int) ($order->released_quantity ?: $order->ordered_quantity),
            'customer' => $order->customerOrder?->customer?->name,
            'salesOrder' => $order->customerOrder?->order_number,
            'due' => $order->customerOrder?->requested_delivery_date?->format('Y-m-d'),
            'supplierId' => $spec->supplier_id,
            'supplier' => $spec->supplier->name,
            'catalog' => $spec->catalog_number ?: $spec->catalog_name,
            'fabric' => $order->fabricMaterial->name_ar,
            'colorSupplier' => $order->fabric_supplier_color_code ?? $order->fabricColor?->supplier_color_code,
            'colorInternal' => $order->fabric_color_code ?? $order->fabricColor?->color_code,
            'colorName' => $order->fabricColor?->color_name_ar,
            'hex' => $order->fabricColor?->hex_code,
            'key' => $order->fabric_material_id.'-'.$order->fabric_color_id,
            'meters' => old('items.'.$order->id.'.meters', $row['planned']),
            'fromRecipe' => $row['planned'] !== null,
            'selected' => (bool) old('items.'.$order->id.'.selected', false),
        ];
    });
    $createdIds = session('created_purchase_orders', []);
@endphp

<div class="d-flex flex-column gap-3">
    <div class="page-header-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="min-w-0">
                <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                    <i class="fas fa-scroll text-primary fs-5" aria-hidden="true"></i> طلبات القماش من الموردين
                </h1>
                <p class="page-header-subtitle text-muted mb-0 fs-7">حدّد الأسرّة، فيجمع النظام أمتار القماش لكل كتالوج ولون، وينشئ طلباً لكل مورد جاهزاً للطباعة.</p>
            </div>
            @if (auth()->user()->isAdministrator())
                <a href="{{ route('settings.company.edit') }}" class="btn btn-outline-secondary"><i class="fas fa-building me-1" aria-hidden="true"></i> بيانات الشركة في المطبوعات</a>
            @endif
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger mb-0" role="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if ($createdIds !== [])
        <div class="card-factory pipeline-result is-success" role="status">
            <p class="fw-bold mb-2"><i class="fas fa-circle-check text-success me-1" aria-hidden="true"></i> أُنشئت طلبات القماش. نزّل ملف PDF لكل مورد وأرسله له:</p>
            <div class="d-flex flex-wrap gap-2">
                @foreach ($recentOrders->whereIn('id', $createdIds) as $created)
                    <a href="{{ route('purchasing.fabric-orders.pdf', $created) }}" class="btn btn-primary btn-sm">
                        <i class="fas fa-file-pdf me-1" aria-hidden="true"></i> {{ $created->supplier?->name }} — <bdi>{{ $created->purchase_order_number }}</bdi>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('purchasing.fabric-orders.store') }}"
          x-data="{
              items: @js($items),
              extra: {{ (float) old('extra_percent', 0) }},
              busy: false,
              get picked() { return this.items.filter(i => i.selected); },
              get missingMeters() { return this.picked.filter(i => !(Number(i.meters) > 0)).length; },
              get summary() {
                  const suppliers = {};
                  for (const item of this.picked) {
                      const s = suppliers[item.supplierId] ??= { name: item.supplier, groups: {} };
                      const g = s.groups[item.key] ??= { catalog: item.catalog, fabric: item.fabric, color: item.colorSupplier || item.colorInternal, colorName: item.colorName, beds: 0, meters: 0 };
                      g.beds += item.quantity;
                      g.meters += Number(item.meters) || 0;
                  }
                  return Object.values(suppliers).map(s => ({ ...s, groups: Object.values(s.groups) }));
              },
              ordered(meters) { return Math.ceil(Math.round(meters * (1 + (Number(this.extra) || 0) / 100) * 10000) / 10000 * 2) / 2; },
              fmt(n) { return Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 }); },
              toggleAll(on) { this.items.forEach(i => i.selected = on); },
          }"
          @submit="busy = true">
        @csrf

        <div class="row g-3">
            <div class="col-xl-8">
                <section class="card-factory p-0" aria-labelledby="beds-heading">
                    <div class="pipeline-list-head">
                        <h2 id="beds-heading" class="h6 fw-bold mb-0">أسرّة بانتظار طلب القماش <span class="count">{{ $items->count() }}</span></h2>
                        @if ($items->isNotEmpty())
                            <label class="d-flex align-items-center gap-2 mb-0">
                                <input type="checkbox" class="form-check-input pipeline-check" :checked="items.length && picked.length === items.length" @change="toggleAll($event.target.checked)" aria-label="تحديد الكل">
                                <span class="fs-7 fw-semibold">تحديد الكل</span>
                            </label>
                        @endif
                    </div>

                    @if ($items->isEmpty())
                        <div class="empty-state" role="status">
                            <i class="fas fa-circle-check text-success fs-2 mb-3 d-block" aria-hidden="true"></i>
                            <p class="fw-semibold mb-1">لا توجد أسرّة بانتظار طلب القماش.</p>
                            <p class="fs-7 mb-0">تظهر هنا أوامر الإنتاج المفتوحة التي حُدد قماشها ولونها ولم يُطلب قماشها بعد.</p>
                        </div>
                    @else
                        <div class="pipeline-list">
                            <template x-for="item in items" :key="item.id">
                                <div class="pipeline-item fabric-item" :class="{ 'is-picked': item.selected }">
                                    <input type="checkbox" class="form-check-input pipeline-check" :name="`items[${item.id}][selected]`" value="1" x-model="item.selected" :aria-label="'تحديد ' + item.number">
                                    <div class="pipeline-item-main" @click="item.selected = !item.selected">
                                        <span class="pipeline-item-title" x-text="item.product"></span>
                                        <span class="pipeline-item-ref"><bdi x-text="item.number"></bdi> · <span x-text="item.customer"></span> · <span x-text="item.quantity + ' سرير'"></span></span>
                                        <span class="pipeline-item-meta">
                                            <span class="status-chip status-chip-primary"><i class="fas fa-truck" aria-hidden="true"></i> <span x-text="item.supplier"></span></span>
                                            <span>كتالوج <strong x-text="item.catalog"></strong></span>
                                            <span><span class="color-dot" :style="`background: ${item.hex || '#cbd5e1'}`" aria-hidden="true"></span> لون <strong dir="ltr" x-text="item.colorSupplier || item.colorInternal"></strong> <span x-text="item.colorName ? '(' + item.colorName + ')' : ''"></span></span>
                                            <template x-if="item.due"><span class="text-muted"><i class="far fa-calendar" aria-hidden="true"></i> <bdi x-text="item.due"></bdi></span></template>
                                        </span>
                                    </div>
                                    <div class="fabric-meters">
                                        <label class="visually-hidden" :for="`meters-${item.id}`" x-text="'أمتار القماش لـ ' + item.number"></label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" step="0.01" min="0" class="form-control text-center" :id="`meters-${item.id}`" :name="`items[${item.id}][meters]`" x-model="item.meters" :class="{ 'is-invalid': item.selected && !(Number(item.meters) > 0) }" placeholder="0">
                                            <span class="input-group-text">متر</span>
                                        </div>
                                        <small class="d-block mt-1" :class="item.fromRecipe ? 'text-success' : 'text-warning-emphasis'" x-text="item.fromRecipe ? 'محسوبة من الوصفة' : 'أدخل الكمية يدوياً'"></small>
                                    </div>
                                </div>
                            </template>
                        </div>
                    @endif
                </section>

                @if ($unlinkedRows->isNotEmpty())
                    <section class="card-factory p-3 mt-3" aria-labelledby="unlinked-heading">
                        <h2 id="unlinked-heading" class="h6 fw-bold text-dark mb-1"><i class="fas fa-link-slash text-warning me-1" aria-hidden="true"></i> قماش غير مربوط بمورد وكتالوج <span class="count">{{ $unlinkedRows->count() }}</span></h2>
                        <p class="fs-7 text-muted mb-2">اربط قماش هذه الطلبات بصنف قماش مسجّل ولونه من صفحة طلب العميل، لتظهر في القائمة أعلاه.</p>
                        <ul class="list-unstyled mb-0 d-flex flex-column gap-2">
                            @foreach ($unlinkedRows as $row)
                                <li class="d-flex flex-wrap justify-content-between align-items-center gap-2 border rounded-3 p-2">
                                    <div class="min-w-0">
                                        <div class="fw-semibold">{{ $row['order']->display_name }} · <bdi>{{ $row['order']->production_order_number }}</bdi></div>
                                        <div class="fs-8 text-muted">{{ $row['order']->customerOrderLine?->fabric_notes ?: 'لم يُحدد القماش أو اللون' }}</div>
                                    </div>
                                    <a href="{{ route('sales.orders.show', $row['order']->customerOrder) }}" class="btn btn-sm btn-outline-primary">ربط القماش</a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>

            <div class="col-xl-4">
                <div class="company-preview-sticky d-flex flex-column gap-3">
                    <section class="card-factory p-3" aria-labelledby="summary-heading" aria-live="polite">
                        <h2 id="summary-heading" class="h6 fw-bold text-dark mb-2">ملخص الطلب</h2>
                        <template x-if="picked.length === 0">
                            <p class="fs-7 text-muted mb-0">حدّد الأسرّة ليظهر هنا مجموع الأمتار لكل مورد وكتالوج ولون.</p>
                        </template>
                        <template x-for="supplier in summary" :key="supplier.name">
                            <div class="fabric-summary-supplier">
                                <div class="fw-bold mb-1"><i class="fas fa-truck text-primary me-1" aria-hidden="true"></i> <span x-text="supplier.name"></span></div>
                                <table class="table table-sm mb-2 fs-8">
                                    <thead><tr><th>الكتالوج / اللون</th><th class="text-center">أسرّة</th><th class="text-center">متر</th></tr></thead>
                                    <tbody>
                                        <template x-for="group in supplier.groups" :key="group.catalog + group.color">
                                            <tr>
                                                <td><span x-text="group.catalog"></span> / <strong dir="ltr" x-text="group.color"></strong></td>
                                                <td class="text-center" x-text="group.beds"></td>
                                                <td class="text-center fw-bold text-nowrap">
                                                    <span x-text="fmt(ordered(group.meters))"></span>
                                                    <span class="d-block text-muted fw-normal" x-show="Number(extra) > 0" x-text="'الصافي ' + fmt(group.meters)"></span>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                        <p class="fs-8 text-danger mb-0" x-show="missingMeters > 0" x-text="missingMeters + ' سرير محدد بلا كمية — أدخل الأمتار.'"></p>
                    </section>

                    <section class="card-factory p-3" aria-labelledby="options-heading">
                        <h2 id="options-heading" class="h6 fw-bold text-dark mb-2">خيارات الطلب</h2>
                        <p class="fs-7 text-muted mb-2"><i class="fas fa-industry me-1" aria-hidden="true"></i> جهة الاستلام: <strong class="text-dark">المصنع</strong> — يُستلم القماش في مخزون المواد الخام.</p>
                        <div class="mb-2">
                            <label for="extra_percent" class="form-label fs-7">نسبة احتياط للهدر</label>
                            <div class="input-group">
                                <input id="extra_percent" type="number" name="extra_percent" min="0" max="50" step="1" class="form-control" x-model="extra">
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">تُضاف للمجموع، ثم تُقرّب الكمية لأقرب نصف متر للأعلى.</div>
                        </div>
                        <div class="mb-3">
                            <label for="notes" class="form-label fs-7">ملاحظات للمورد</label>
                            <textarea id="notes" name="notes" rows="2" class="form-control" maxlength="1000" placeholder="مثال: التسليم خلال 5 أيام عمل.">{{ old('notes') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold" :disabled="picked.length === 0 || missingMeters > 0 || busy">
                            <span x-show="busy" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                            <i class="fas fa-file-circle-plus me-1" aria-hidden="true" x-show="!busy"></i>
                            <span x-text="picked.length ? 'إنشاء طلبات القماش (' + summary.length + ' مورد)' : 'إنشاء طلبات القماش'"></span>
                        </button>
                    </section>
                </div>
            </div>
        </div>
    </form>

    <section class="card-factory p-0" aria-labelledby="recent-heading">
        <div class="pipeline-list-head">
            <h2 id="recent-heading" class="h6 fw-bold mb-0">طلبات القماش السابقة</h2>
        </div>
        @if ($recentOrders->isEmpty())
            <p class="text-muted fs-7 p-3 mb-0">لم تُنشأ طلبات قماش بعد.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>رقم الطلب</th><th>المورد</th><th>التاريخ</th><th class="text-center">الأمتار</th><th>الحالة</th><th class="text-end">إجراء</th></tr></thead>
                    <tbody>
                        @foreach ($recentOrders as $recent)
                            <tr>
                                <td class="text-nowrap"><a href="{{ route('purchasing.orders.show', $recent) }}" class="fw-bold text-decoration-none"><bdi>{{ $recent->purchase_order_number }}</bdi></a></td>
                                <td>{{ $recent->supplier?->name }}</td>
                                <td class="text-nowrap">{{ $recent->order_date?->format('Y-m-d') }}</td>
                                <td class="text-center">{{ rtrim(rtrim(number_format((float) $recent->lines_sum_ordered_quantity, 2), '0'), '.') }}</td>
                                <td><span class="badge bg-light text-dark border">{{ ['DRAFT' => 'مسودة', 'PENDING_APPROVAL' => 'بانتظار الاعتماد', 'APPROVED' => 'معتمد', 'SENT' => 'مُرسل للمورد', 'PARTIALLY_RECEIVED' => 'مستلم جزئياً', 'RECEIVED' => 'مستلم', 'CLOSED' => 'مغلق', 'CANCELLED' => 'ملغى'][$recent->status] ?? $recent->status }}</span></td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('purchasing.fabric-orders.print', $recent) }}" target="_blank" class="btn btn-sm btn-outline-primary text-nowrap"><i class="fas fa-eye me-1" aria-hidden="true"></i> معاينة</a>
                                        <a href="{{ route('purchasing.fabric-orders.pdf', $recent) }}" class="btn btn-sm btn-primary text-nowrap"><i class="fas fa-file-pdf me-1" aria-hidden="true"></i> PDF</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
