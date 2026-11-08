@extends('layouts.app')

@section('title', 'طلب توريد مراتب - مصنع مفروشات سدير')
@section('page-title', 'المراتب')

@section('content')
@php
    $supply = app(\App\Services\ReadyProductSupplyService::class);
    $qty = fn ($value) => rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
    $items = $demand->map(function ($line) use ($supply, $overview) {
        $setting = $line->material->supplySetting;
        $stock = $overview->get($line->material_id);

        return [
            'id' => $line->id,
            'order' => $line->customerOrder->order_number,
            'customer' => $line->customerOrder->customer?->name,
            'due' => $line->customerOrder->requested_delivery_date?->format('Y-m-d'),
            'bundle' => $line->parentLine?->line_type === 'BUNDLE' ? ($line->parentLine->external_product_name ?: 'بكج') : null,
            'product' => $supply->productName($setting),
            'size' => $line->material->ready_option_label ?: 'مقاس واحد',
            'materialId' => $line->material_id,
            'supplierId' => $setting->supplier_id,
            'supplier' => $setting->supplier->name,
            'remaining' => (float) $line->remaining_quantity,
            'quantity' => (float) old('items.'.$line->id.'.quantity', $line->remaining_quantity),
            'selected' => (bool) old('items.'.$line->id.'.selected', false),
            'onHand' => $stock['on_hand'] ?? 0,
            'atSupplier' => $stock['at_supplier'] ?? 0,
        ];
    })->values();
    $createdIds = session('created_purchase_orders', []);
@endphp

<div class="d-flex flex-column gap-3">
    @include('purchasing.mattresses.partials.tabs', ['subtitle' => 'اختر طلبات العملاء التي فيها مراتب، فيجمع النظام الكمية لكل مقاس، وينشئ طلب توريد لكل مورد.'])

    @if ($createdIds !== [])
        <div class="card-factory pipeline-result is-success" role="status">
            <p class="fw-bold mb-2"><i class="fas fa-circle-check text-success me-1" aria-hidden="true"></i> أُنشئت طلبات التوريد. نزّل ملف PDF لكل مورد وأرسله له:</p>
            <div class="d-flex flex-wrap gap-2">
                @foreach ($recentOrders->whereIn('id', $createdIds) as $created)
                    <a href="{{ route('purchasing.mattresses.orders.pdf', $created) }}" class="btn btn-primary btn-sm"><i class="fas fa-file-pdf me-1" aria-hidden="true"></i> {{ $created->supplier?->name }} — <bdi>{{ $created->purchase_order_number }}</bdi></a>
                @endforeach
            </div>
        </div>
    @endif

    @if ($untrackedSupplierCount > 0)
        <div class="alert alert-warning mb-0 fs-7" role="status">
            <i class="fas fa-circle-info me-1" aria-hidden="true"></i> {{ $untrackedSupplierCount }} مقاس متابَع بلا مورد، فلا تظهر طلباته هنا. حدد المورد من <a href="{{ route('purchasing.mattresses.stock') }}">المخزون والتنبيهات</a>.
        </div>
    @endif

    <form method="POST" action="{{ route('purchasing.mattresses.orders.store') }}"
          x-data="{
              items: @js($items),
              busy: false,
              get picked() { return this.items.filter(i => i.selected); },
              get invalid() { return this.picked.filter(i => !(Number(i.quantity) > 0) || Number(i.quantity) > i.remaining).length; },
              get summary() {
                  const suppliers = {};
                  for (const item of this.picked) {
                      const s = suppliers[item.supplierId] ??= { name: item.supplier, groups: {} };
                      const g = s.groups[item.materialId] ??= { product: item.product, size: item.size, quantity: 0, orders: 0, onHand: item.onHand };
                      g.quantity += Number(item.quantity) || 0;
                      g.orders += 1;
                  }
                  return Object.values(suppliers).map(s => ({ ...s, groups: Object.values(s.groups) }));
              },
              fmt(n) { return Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 }); },
              toggleAll(on) { this.items.forEach(i => i.selected = on); },
          }"
          @submit="busy = true">
        @csrf
        <div class="row g-3">
            <div class="col-xl-8">
                <section class="card-factory p-0" aria-labelledby="demand-heading">
                    <div class="pipeline-list-head">
                        <h2 id="demand-heading" class="h6 fw-bold mb-0">مراتب في طلبات مفتوحة لم تُطلب من المورد <span class="count">{{ $items->count() }}</span></h2>
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
                            <p class="fw-semibold mb-1">لا توجد مراتب بانتظار الطلب من المورد.</p>
                            <p class="fs-7 mb-0">تظهر هنا بنود المراتب في طلبات العملاء والبكجات المفتوحة، لمقاسات متابَعة لها مورد.</p>
                        </div>
                    @else
                        <div class="pipeline-list">
                            <template x-for="item in items" :key="item.id">
                                <div class="pipeline-item fabric-item" :class="{ 'is-picked': item.selected }">
                                    <input type="checkbox" class="form-check-input pipeline-check" :name="`items[${item.id}][selected]`" value="1" x-model="item.selected" :aria-label="'تحديد ' + item.order">
                                    <div class="pipeline-item-main" @click="item.selected = !item.selected">
                                        <span class="pipeline-item-title"><span x-text="item.product"></span> — <bdi x-text="item.size"></bdi></span>
                                        <span class="pipeline-item-ref"><bdi x-text="item.order"></bdi> · <span x-text="item.customer"></span></span>
                                        <span class="pipeline-item-meta">
                                            <span class="status-chip status-chip-primary"><i class="fas fa-truck" aria-hidden="true"></i> <span x-text="item.supplier"></span></span>
                                            <template x-if="item.bundle"><span class="status-chip status-chip-info"><i class="fas fa-box-open" aria-hidden="true"></i> <span x-text="item.bundle"></span></span></template>
                                            <span class="text-muted">لدينا <strong x-text="fmt(item.onHand)"></strong> · لدى المورد <strong x-text="fmt(item.atSupplier)"></strong></span>
                                            <template x-if="item.due"><span class="text-muted"><i class="far fa-calendar" aria-hidden="true"></i> <bdi x-text="item.due"></bdi></span></template>
                                        </span>
                                    </div>
                                    <div class="fabric-meters">
                                        <label class="visually-hidden" :for="`qty-${item.id}`" x-text="'الكمية لـ ' + item.order"></label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" step="1" min="1" :max="item.remaining" class="form-control text-center" :id="`qty-${item.id}`" :name="`items[${item.id}][quantity]`" x-model="item.quantity" :class="{ 'is-invalid': item.selected && (!(Number(item.quantity) > 0) || Number(item.quantity) > item.remaining) }">
                                            <span class="input-group-text">حبة</span>
                                        </div>
                                        <small class="d-block mt-1 text-muted" x-text="'المطلوب في الطلب ' + fmt(item.remaining)"></small>
                                    </div>
                                </div>
                            </template>
                        </div>
                    @endif
                </section>
            </div>

            <div class="col-xl-4">
                <div class="company-preview-sticky d-flex flex-column gap-3">
                    <section class="card-factory p-3" aria-labelledby="mattress-summary-heading" aria-live="polite">
                        <h2 id="mattress-summary-heading" class="h6 fw-bold text-dark mb-2">ملخص الطلب</h2>
                        <template x-if="picked.length === 0">
                            <p class="fs-7 text-muted mb-0">حدّد الطلبات ليظهر هنا مجموع كل مقاس لكل مورد.</p>
                        </template>
                        <template x-for="supplier in summary" :key="supplier.name">
                            <div class="fabric-summary-supplier">
                                <div class="fw-bold mb-1"><i class="fas fa-truck text-primary me-1" aria-hidden="true"></i> <span x-text="supplier.name"></span></div>
                                <table class="table table-sm mb-2 fs-8">
                                    <thead><tr><th>المرتبة / المقاس</th><th class="text-center">طلبات</th><th class="text-center">الكمية</th></tr></thead>
                                    <tbody>
                                        <template x-for="group in supplier.groups" :key="group.product + group.size">
                                            <tr>
                                                <td><span x-text="group.product"></span> <strong dir="ltr" x-text="group.size"></strong><div class="text-muted" x-show="group.onHand > 0" x-text="'لدينا في المستودع ' + fmt(group.onHand)"></div></td>
                                                <td class="text-center" x-text="group.orders"></td>
                                                <td class="text-center fw-bold" x-text="fmt(group.quantity)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                        <p class="fs-8 text-danger mb-0" x-show="invalid > 0" x-text="invalid + ' بند كميته غير صحيحة.'"></p>
                    </section>

                    <section class="card-factory p-3" aria-labelledby="mattress-options-heading">
                        <h2 id="mattress-options-heading" class="h6 fw-bold text-dark mb-2">خيارات الطلب</h2>
                        <p class="fs-7 text-muted mb-2"><i class="fas fa-industry me-1" aria-hidden="true"></i> جهة الاستلام: <strong class="text-dark">المصنع</strong> — تدخل المراتب مخزون المنتجات الجاهزة، وتُخصم من رصيد المورد عند الاستلام.</p>
                        <div class="mb-3">
                            <label for="mattress-notes" class="form-label fs-7">ملاحظات للمورد</label>
                            <textarea id="mattress-notes" name="notes" rows="2" class="form-control" maxlength="1000" placeholder="مثال: التسليم صباح الأحد.">{{ old('notes') }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold" :disabled="picked.length === 0 || invalid > 0 || busy">
                            <span x-show="busy" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                            <span x-text="picked.length ? 'إنشاء طلبات التوريد (' + summary.length + ' مورد)' : 'إنشاء طلبات التوريد'"></span>
                        </button>
                    </section>
                </div>
            </div>
        </div>
    </form>

    <section class="card-factory p-0" aria-labelledby="mattress-recent-heading">
        <div class="pipeline-list-head"><h2 id="mattress-recent-heading" class="h6 fw-bold mb-0">طلبات التوريد السابقة</h2></div>
        @if ($recentOrders->isEmpty())
            <p class="text-muted fs-7 p-3 mb-0">لم تُنشأ طلبات توريد مراتب بعد.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>رقم الطلب</th><th>المورد</th><th>التاريخ</th><th class="text-center">الكمية</th><th>الحالة</th><th class="text-end">إجراء</th></tr></thead>
                    <tbody>
                        @foreach ($recentOrders as $recent)
                            <tr>
                                <td class="text-nowrap"><a href="{{ route('purchasing.orders.show', $recent) }}" class="fw-bold text-decoration-none"><bdi>{{ $recent->purchase_order_number }}</bdi></a></td>
                                <td>{{ $recent->supplier?->name }}</td>
                                <td class="text-nowrap">{{ $recent->order_date?->format('Y-m-d') }}</td>
                                <td class="text-center">{{ $qty($recent->lines_sum_ordered_quantity) }}</td>
                                <td><span class="badge bg-light text-dark border">{{ ['DRAFT' => 'مسودة', 'PENDING_APPROVAL' => 'بانتظار الاعتماد', 'APPROVED' => 'معتمد', 'SENT' => 'مُرسل للمورد', 'PARTIALLY_RECEIVED' => 'مستلم جزئياً', 'RECEIVED' => 'مستلم', 'CLOSED' => 'مغلق', 'CANCELLED' => 'ملغى'][$recent->status] ?? $recent->status }}</span></td>
                                <td class="text-end"><a href="{{ route('purchasing.mattresses.orders.pdf', $recent) }}" class="btn btn-sm btn-primary text-nowrap"><i class="fas fa-file-pdf me-1" aria-hidden="true"></i> PDF</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
