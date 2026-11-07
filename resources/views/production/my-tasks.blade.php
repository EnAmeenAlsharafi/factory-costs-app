@extends('layouts.app')

@section('title', 'مهامي - مصنع مفروشات سدير')
@section('page-title', 'مهامي')

@section('content')
@php
    $user = auth()->user();
    $priorityLabels = ['NORMAL' => 'عادي', 'URGENT' => 'عاجل', 'VIP' => 'VIP'];
@endphp

<div class="d-flex flex-column gap-4">
    <div class="page-header-card">
        <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">
            <i class="fas fa-user-check text-primary fs-5" aria-hidden="true"></i>
            <span>مهامي</span>
        </h1>
        <p class="page-header-subtitle text-muted mb-0 fs-7">
            المهام المُسندة إليك{{ $user->department ? ' — قسم '.$user->department->name_ar : '' }}، والمهام الجاهزة التي يمكنك استلامها.
        </p>
    </div>

    <section aria-labelledby="my-tasks-heading">
        <h2 id="my-tasks-heading" class="h6 fw-bold text-dark mb-3">مهامي الحالية <span class="count">{{ $myTasks->count() }}</span></h2>

        @if ($myTasks->isEmpty())
            <div class="card-factory empty-state" role="status">
                <i class="fas fa-mug-hot text-secondary fs-2 mb-3 d-block" aria-hidden="true"></i>
                <p class="mb-0 fw-semibold">لا توجد مهام مُسندة إليك حالياً. استلم مهمة من القائمة أدناه.</p>
            </div>
        @else
            <div class="task-list task-grid">
                @foreach ($myTasks as $op)
                    @php
                        $po = $op->productionOrder;
                        $productName = $po->display_name;
                        $remaining = max(0, $op->required_quantity - $op->completed_quantity);
                        $isRework = $op->targetReworkActions->isNotEmpty();
                        $tone = \App\Services\StatusPresenter::present('operation', $op->status)['tone'];
                    @endphp
                    <article class="task-card tone-{{ $tone }} {{ $isRework ? 'is-rework' : '' }}" aria-labelledby="my-task-title-{{ $op->id }}">
                        <div class="task-card-head">
                            <div class="min-w-0">
                                <h3 id="my-task-title-{{ $op->id }}" class="task-card-title">{{ $productName }}</h3>
                                <div class="task-card-ref">
                                    {{ $op->operation_name_snapshot }} &bull; <span class="ltr-isolate">{{ $po->production_order_number }}</span>
                                </div>
                            </div>
                            <x-status-badge domain="operation" :status="$op->status" />
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            @if ($isRework)
                                <span class="rework-flag"><i class="fas fa-rotate" aria-hidden="true"></i> إعادة عمل</span>
                            @endif
                            @if (in_array($po->priority, ['URGENT', 'VIP'], true))
                                <span class="status-chip status-chip-danger"><i class="fas fa-bolt" aria-hidden="true"></i> {{ $priorityLabels[$po->priority] }}</span>
                            @endif
                        </div>
                        @if ($op->status === 'BLOCKED' && $op->activeBlock)
                            <div class="alert alert-danger py-2 mb-0 fs-8" role="alert">
                                <i class="fas fa-hand me-1" aria-hidden="true"></i> متوقف: {{ $op->activeBlock->reason_arabic }}
                            </div>
                        @endif
                        <div class="qty-trio" aria-label="الكميات">
                            <div><small>المطلوب</small><strong>{{ $op->required_quantity }}</strong></div>
                            <div><small>المنجز</small><strong>{{ $op->completed_quantity }}</strong></div>
                            <div class="is-remaining"><small>المتبقي</small><strong>{{ $remaining }}</strong></div>
                        </div>
                        <div class="task-card-actions">
                            <button type="button" class="btn btn-factory-primary" data-bs-toggle="modal" data-bs-target="#task-sheet-{{ $op->id }}">
                                <i class="fas fa-folder-open" aria-hidden="true"></i> فتح المهمة
                            </button>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    @if ($user->can('production.operation.claim'))
        <section aria-labelledby="claimable-heading">
            <h2 id="claimable-heading" class="h6 fw-bold text-dark mb-3">مهام جاهزة للاستلام <span class="count">{{ $claimable->count() }}</span></h2>

            @if ($claimable->isEmpty())
                <div class="card-factory empty-state" role="status">
                    <i class="fas fa-circle-check text-success fs-2 mb-3 d-block" aria-hidden="true"></i>
                    <p class="mb-0 fw-semibold">لا توجد مهام جاهزة غير مُسندة في قسمك حالياً.</p>
                </div>
            @else
                <div class="task-list task-grid">
                    @foreach ($claimable as $op)
                        @php
                            $po = $op->productionOrder;
                            $productName = $po->display_name;
                        @endphp
                        <article class="task-card tone-info" aria-labelledby="claimable-title-{{ $op->id }}">
                            <div class="task-card-head">
                                <div class="min-w-0">
                                    <h3 id="claimable-title-{{ $op->id }}" class="task-card-title">{{ $productName }}</h3>
                                    <div class="task-card-ref">
                                        {{ $op->operation_name_snapshot }} &bull; <span class="ltr-isolate">{{ $po->production_order_number }}</span>
                                    </div>
                                </div>
                                <x-status-badge domain="operation" :status="$op->status" />
                            </div>
                            @if (in_array($po->priority, ['URGENT', 'VIP'], true))
                                <span class="status-chip status-chip-danger"><i class="fas fa-bolt" aria-hidden="true"></i> {{ $priorityLabels[$po->priority] }}</span>
                            @endif
                            <div class="task-card-actions">
                                <form action="{{ route('production.operations.claim', $op) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-primary fw-bold w-100">
                                        <i class="fas fa-hand-pointer" aria-hidden="true"></i> استلام المهمة
                                    </button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    {{-- Task sheets for "my tasks" reuse the same modal partial as the department queue. --}}
    @foreach ($myTasks as $op)
        @include('production.queue.partials.task-sheet', ['op' => $op, 'priorityLabels' => $priorityLabels])
    @endforeach
</div>
@endsection
