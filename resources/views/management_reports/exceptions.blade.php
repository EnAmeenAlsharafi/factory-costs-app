@extends('layouts.app')

@section('title', 'الاستثناءات التشغيلية')
@section('page-title', 'الاستثناءات التشغيلية')

@section('content')
<div class="report-workspace d-flex flex-column gap-3">
    <x-report.header title="الاستثناءات التشغيلية" icon="fa-triangle-exclamation"
        subtitle="ما يعطل العمل الآن. العمر = أيام منذ الإنشاء أو منذ التاريخ المتوقع. تظهر الخطورة فقط حيث يحددها السجل الأصلي." />

    @forelse ($groups as $key => $group)
        <section class="card-factory p-0" aria-labelledby="exc-{{ $key }}">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 border-bottom">
                <h2 id="exc-{{ $key }}" class="h6 fw-bold mb-0">{{ $group['title'] }}</h2>
                <span class="d-flex gap-2 align-items-center">
                    <span class="status-chip status-chip-secondary">{{ $group['area'] }}</span>
                    <span class="status-chip status-chip-danger">{{ number_format($group['count']) }}</span>
                </span>
            </div>
            <div class="report-inbox" role="list" aria-label="{{ $group['title'] }}">
                @foreach ($group['items'] as $item)
                    <article class="report-inbox-item" role="listitem">
                        <div><a href="{{ $item['url'] }}" class="fw-semibold"><bdi>{{ $item['reference'] }}</bdi></a><div class="report-inbox-meta">{{ $group['area'] }}</div></div>
                        <div class="fs-7">{{ $item['context'] }}</div>
                        <div class="report-inbox-meta">العمر: <bdi>{{ $item['age'] ?? '—' }}</bdi> يوم</div>
                        <div>@if ($item['severity'])<span class="status-chip status-chip-{{ $item['severity']['tone'] }}">{{ $item['severity']['label'] }}</span>@else<span class="report-inbox-meta">الخطورة غير محددة</span>@endif</div>
                        <a href="{{ $item['url'] }}" class="btn btn-outline-secondary report-no-print" aria-label="متابعة {{ $item['reference'] }}">متابعة <i class="fas fa-angle-left" aria-hidden="true"></i></a>
                    </article>
                @endforeach
            </div>
            @if ($group['count'] > $group['items']->count())
                <div class="px-3 py-2 fs-8 text-muted border-top">يُعرض أول {{ $group['items']->count() }} من {{ number_format($group['count']) }}.</div>
            @endif
        </section>
    @empty
        <div class="card-factory empty-state" role="status">
            <i class="fas fa-circle-check text-success fs-2 mb-3 d-block" aria-hidden="true"></i>
            <p class="mb-0 fw-semibold">لا توجد استثناءات تشغيلية مفتوحة ضمن صلاحياتك حالياً.</p>
        </div>
    @endforelse
</div>
@endsection
