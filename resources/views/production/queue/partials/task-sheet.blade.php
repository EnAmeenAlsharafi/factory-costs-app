@php
    $po = $op->productionOrder;
    $productName = $po->is_custom_design ? $po->custom_design_name : ($po->productModel?->name_ar ?? 'منتج');
    $remaining = max(0, $op->required_quantity - $op->completed_quantity);
    $canRecord = auth()->user()->can('production.update_progress') && $remaining > 0 && $op->status !== 'COMPLETED';
    $colorCode = $po->fabric_color_code ?? $po->fabricColor?->color_code;
@endphp
<div class="modal fade" id="task-sheet-{{ $op->id }}" tabindex="-1" aria-labelledby="task-sheet-title-{{ $op->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down modal-lg">
        <form action="{{ route('production.operations.progress', $op) }}" method="POST" class="modal-content"
              x-data="{ qty: {{ $remaining > 0 ? 1 : 0 }}, remaining: {{ $remaining }} }"
              :data-confirm="Number(qty) >= remaining ? 'سيتم تسجيل كامل الكمية المتبقية وإكمال هذه العملية. هل تريد المتابعة؟' : null">
            @csrf
            <div class="modal-header">
                <div class="min-w-0">
                    <h2 class="modal-title h5 fw-bold mb-0" id="task-sheet-title-{{ $op->id }}">{{ $productName }}</h2>
                    <div class="fs-7 text-muted">{{ $op->operation_name_snapshot }} &bull; <span class="ltr-isolate">{{ $po->production_order_number }}</span></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق المهمة"></button>
            </div>

            <div class="modal-body d-flex flex-column gap-3">
                <div class="d-flex flex-wrap gap-2">
                    <x-status-badge domain="operation" :status="$op->status" size="lg" />
                    @if ($op->targetReworkActions->isNotEmpty())
                        <span class="rework-flag"><i class="fas fa-rotate" aria-hidden="true"></i> إعادة عمل — ليست إنتاجاً عادياً</span>
                    @endif
                    @if (in_array($po->priority, ['URGENT', 'VIP'], true))
                        <span class="status-chip status-chip-danger status-chip-lg"><i class="fas fa-bolt" aria-hidden="true"></i> {{ $priorityLabels[$po->priority] ?? $po->priority }}</span>
                    @endif
                </div>

                {{-- Manufacturing specs --}}
                <section aria-labelledby="task-specs-{{ $op->id }}">
                    <h3 id="task-specs-{{ $op->id }}" class="h6 fw-bold text-dark mb-2">مواصفات التصنيع</h3>
                    <dl class="detail-list">
                        <div><dt>المنتج</dt><dd>{{ $productName }}</dd></div>
                        <div><dt>المقاس المطلوب</dt><dd><span class="ltr-isolate">{{ (int) $po->requested_width_cm }}×{{ (int) $po->requested_length_cm }}</span> سم</dd></div>
                        @if ($po->reference_width_cm && ((int) $po->reference_width_cm !== (int) $po->requested_width_cm || (int) $po->reference_length_cm !== (int) $po->requested_length_cm))
                            <div><dt>المقاس المرجعي للتصنيع</dt><dd><span class="ltr-isolate">{{ (int) $po->reference_width_cm }}×{{ (int) $po->reference_length_cm }}</span> سم</dd></div>
                        @endif
                        <div><dt>السحارة</dt><dd>{{ $po->has_storage ? 'مع سحارة' : 'بدون سحارة' }}</dd></div>
                        <div><dt>القماش</dt><dd>{{ $po->fabricMaterial?->name_ar ?? '—' }}</dd></div>
                        <div><dt>مورد القماش</dt><dd>{{ $po->fabricSupplier?->name ?? '—' }}</dd></div>
                        <div>
                            <dt>اللون</dt>
                            <dd>
                                @if ($colorCode)
                                    <span class="color-dot" style="background: {{ $po->fabricColor?->hex_code ?? '#cbd5e1' }};" aria-hidden="true"></span>
                                    <span class="ltr-isolate">{{ $colorCode }}</span>
                                    @if ($po->fabricColor?->color_name_ar) ({{ $po->fabricColor->color_name_ar }}) @endif
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div><dt>القسم</dt><dd>{{ $op->workCenter?->department?->name_ar ?? '—' }}</dd></div>
                        @if ($po->production_notes)
                            <div class="span-all"><dt>ملاحظات التصنيع</dt><dd class="fw-normal">{{ $po->production_notes }}</dd></div>
                        @endif
                    </dl>
                </section>

                @foreach ($op->targetReworkActions as $rework)
                    <div class="alert alert-warning mb-0 py-2" role="note">
                        <div class="fw-bold"><i class="fas fa-rotate" aria-hidden="true"></i> إعادة عمل <span class="ltr-isolate fs-8">{{ $rework->rework_number }}</span> — الكمية المتأثرة: {{ $rework->quantity }}</div>
                        @if ($rework->qualityIncident?->description)
                            <div class="fs-7">السبب: {{ $rework->qualityIncident->description }}</div>
                        @endif
                        @if ($rework->notes)
                            <div class="fs-7">{{ $rework->notes }}</div>
                        @endif
                    </div>
                @endforeach

                {{-- Progress --}}
                <section aria-labelledby="task-progress-{{ $op->id }}">
                    <h3 id="task-progress-{{ $op->id }}" class="h6 fw-bold text-dark mb-2">الإنجاز</h3>
                    <div class="qty-trio">
                        <div><small>الكمية المطلوبة</small><strong>{{ $op->required_quantity }}</strong></div>
                        <div><small>المنجز سابقاً</small><strong>{{ $op->completed_quantity }}</strong></div>
                        <div class="is-remaining"><small>المتبقي</small><strong>{{ $remaining }}</strong></div>
                    </div>
                </section>

                @if ($canRecord)
                    <div>
                        <label for="added-qty-{{ $op->id }}" class="form-label">الكمية المنجزة الآن <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <button type="button" class="btn btn-outline-secondary" style="min-width: 52px;" @click="qty = Math.max(1, Number(qty) - 1)" aria-label="إنقاص الكمية">
                                <i class="fas fa-minus" aria-hidden="true"></i>
                            </button>
                            <input type="number" id="added-qty-{{ $op->id }}" name="added_quantity" x-model.number="qty"
                                   class="form-control text-center fw-bold" inputmode="numeric" pattern="[0-9]*"
                                   min="1" max="{{ $remaining }}" step="1" required>
                            <button type="button" class="btn btn-outline-secondary" style="min-width: 52px;" @click="qty = Math.min(remaining, Number(qty) + 1)" aria-label="زيادة الكمية">
                                <i class="fas fa-plus" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 gap-2">
                            <small class="text-muted">الحد الأقصى المتاح: {{ $remaining }}</small>
                            <button type="button" class="btn btn-sm btn-outline-primary" @click="qty = remaining">إنجاز كامل المتبقي ({{ $remaining }})</button>
                        </div>
                        <p class="text-danger fs-7 mt-1 mb-0" x-show="Number(qty) > remaining" x-cloak role="alert">
                            الكمية المدخلة تتجاوز المتبقي ({{ $remaining }}).
                        </p>
                    </div>
                    <div>
                        <label for="progress-notes-{{ $op->id }}" class="form-label">ملاحظات (اختياري)</label>
                        <input type="text" id="progress-notes-{{ $op->id }}" name="notes" class="form-control" maxlength="500" autocomplete="off" placeholder="أي ملاحظة فنية...">
                    </div>
                @elseif ($remaining === 0)
                    <div class="alert alert-success mb-0" role="status"><i class="fas fa-circle-check" aria-hidden="true"></i> اكتملت هذه العملية.</div>
                @endif
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">إغلاق</button>
                @if ($canRecord)
                    <button type="submit" class="btn btn-success fw-bold" :disabled="Number(qty) < 1 || Number(qty) > remaining">
                        <i class="fas fa-check-circle" aria-hidden="true"></i> تسجيل التقدم
                    </button>
                @endif
            </div>
        </form>
    </div>
</div>
