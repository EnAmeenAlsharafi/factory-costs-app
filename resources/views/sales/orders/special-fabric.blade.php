@foreach($order->lines->where('special_fabric', true) as $fabricLine)
    <div class="card border-warning mb-3">
        <div class="card-body">
            <h2 class="h5">القماش الخاص — {{ $fabricLine->custom_design_name ?: $fabricLine->productModel?->name_ar }}</h2>
            <p class="mb-2">{{ $fabricLine->fabric_notes }}</p>
            <p class="mb-2">
                @if($fabricLine->fabric_material_id && $fabricLine->fabric_color_id)
                    الربط المخزني: {{ $fabricLine->fabricMaterial?->name_ar }} — {{ $fabricLine->fabricColor?->color_code }}
                    — {{ $fabricLine->fabricSupplier?->name }}
                @else
                    <span class="badge bg-warning text-dark">بانتظار ربط القماش بالمخزون</span>
                @endif
            </p>
            <p class="text-muted">الربط اختياري عند إرسال العرض. قبل الصرف والتنجيد، سجّل القماش بمورده وكتالوجه ولونه، ثم اعتمد استلامه بالمستودع واصرفه على أمر الإنتاج. يبقى وصف العميل والسعر محفوظين.</p>
            @if($order->status !== 'CANCELLED' && (auth()->user()->can('orders.update') || auth()->user()->can('orders.review_production')))
                <form method="POST" action="{{ route('sales.orders.fabric.update', [$order, $fabricLine]) }}" class="d-flex flex-wrap gap-2">
                    @csrf
                    @method('PUT')
                    <label class="visually-hidden" for="special-fabric-{{ $fabricLine->id }}">القماش واللون المعتمدان</label>
                    <select name="fabric_color_id" id="special-fabric-{{ $fabricLine->id }}" class="form-select w-auto flex-grow-1" required>
                        <option value="">اختر القماش واللون المطابقين لطلب العميل</option>
                        @foreach($specialFabricColors as $fabricColor)
                            <option value="{{ $fabricColor->id }}" @selected($fabricLine->fabric_color_id === $fabricColor->id)>
                                {{ $fabricColor->material->name_ar }} — {{ $fabricColor->material->fabricSpec?->catalog_number ?: $fabricColor->material->fabricSpec?->catalog_name }}
                                — {{ $fabricColor->material->fabricSpec?->supplier?->name }} — {{ $fabricColor->color_name_ar }} ({{ $fabricColor->color_code }})
                            </option>
                        @endforeach
                    </select>
                    <button class="btn btn-outline-primary" type="submit">حفظ الربط المخزني</button>
                </form>
            @endif
        </div>
    </div>
@endforeach
