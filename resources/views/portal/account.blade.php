@extends('layouts.portal')
@section('title', 'كشف الحساب')
@section('content')
<h1 class="h4 fw-bold mb-3">كشف الحساب</h1>

<div class="portal-tiles mb-3">
    @foreach([
        ['المتبقي عليك', $summary['outstanding'], 'fa-wallet', 'tone-danger'],
        ['متأخر السداد', $summary['overdue'], 'fa-triangle-exclamation', 'tone-danger'],
        ['رصيد دائن لك', $summary['credit'], 'fa-piggy-bank', 'tone-success'],
        ['الرصيد الافتتاحي', $summary['opening_balance'], 'fa-flag', ''],
        ['قيمة الطلبات التجارية', $summary['commercial_total'], 'fa-receipt', ''],
        ['دفعات مؤكدة', $summary['confirmed_paid'], 'fa-circle-check', ''],
    ] as [$label, $value, $icon, $tone])
        <div class="portal-tile {{ (float) $value > 0 ? $tone : '' }}">
            <div class="portal-tile-label"><i class="fas {{ $icon }}" aria-hidden="true"></i> {{ $label }}</div>
            <div class="portal-tile-value">{{ number_format($value, 2) }} <span class="fs-7 fw-normal text-muted">ر.س</span></div>
        </div>
    @endforeach
</div>

<details class="card border-0 shadow-sm mb-3">
    <summary class="card-body py-3 fw-bold fs-7" style="cursor:pointer;">ما يشمله هذا الكشف</summary>
    <div class="card-body pt-0 text-muted fs-7">يشمل الكشف الدفعات التي أكدها المصنع، بما فيها الدفعات المقدمة غير المخصصة لطلب. المسودات والعروض غير المؤكدة مستبعدة. إعادة منتج للمصنع لا تخصم مبلغًا تلقائيًا؛ تُراجع التسوية المالية مع خدمة العملاء.</div>
</details>

<h2 class="h6 fw-bold mb-2">الحركات المالية</h2>
{{-- Phone: one card per entry (5 numeric columns never fit a phone). Tablet/desktop: the ledger table. --}}
<div class="task-list mobile-cards-only-lg">
    @forelse($entries as $entry)
        <article class="task-card tone-{{ $entry['debit'] > 0 ? 'warning' : 'success' }}">
            <div class="task-card-head">
                <div class="min-w-0">
                    <h3 class="task-card-title">{{ $entry['reference'] }}</h3>
                    <div class="task-card-ref"><span class="ltr-isolate">{{ $entry['date'] ?: 'بداية الحساب' }}</span></div>
                </div>
            </div>
            <dl class="portal-facts mb-0">
                @if($entry['debit'] > 0)
                    <div><dt>مدين (عليك)</dt><dd>{{ number_format($entry['debit'], 2) }} ر.س</dd></div>
                @endif
                @if($entry['credit'] > 0)
                    <div><dt>دائن (سُدّد)</dt><dd>{{ number_format($entry['credit'], 2) }} ر.س</dd></div>
                @endif
                <div><dt>الرصيد بعد الحركة</dt><dd>{{ number_format($entry['balance'], 2) }} ر.س</dd></div>
            </dl>
        </article>
    @empty
        <div class="card-factory empty-state" role="status">
            <i class="fas fa-file-invoice-dollar fs-2 mb-3 d-block opacity-50" aria-hidden="true"></i>
            <p class="mb-0">لا توجد حركات مالية.</p>
        </div>
    @endforelse
</div>

<div class="card has-mobile-cards-lg">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>التاريخ</th><th>البيان</th><th>مدين</th><th>دائن</th><th>الرصيد</th></tr></thead>
            <tbody>
                @forelse($entries as $entry)
                    <tr>
                        <td><span class="ltr-isolate">{{ $entry['date'] ?: 'بداية الحساب' }}</span></td>
                        <td>{{ $entry['reference'] }}</td>
                        <td>{{ number_format($entry['debit'], 2) }}</td>
                        <td>{{ number_format($entry['credit'], 2) }}</td>
                        <td>{{ number_format($entry['balance'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center p-4">لا توجد حركات مالية.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3 d-flex justify-content-center">{{ $entries->links() }}</div>
@endsection
