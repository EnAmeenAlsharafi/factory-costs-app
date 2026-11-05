@extends('layouts.portal')
@section('title', 'كشف الحساب')
@section('content')
<h1 class="h3 mb-3">كشف الحساب</h1>
<div class="row g-3 mb-4">@foreach(['الرصيد الافتتاحي' => $summary['opening_balance'], 'قيمة الطلبات التجارية' => $summary['commercial_total'], 'دفعات مؤكدة' => $summary['confirmed_paid'], 'المبلغ المتبقي عليك' => $summary['outstanding'], 'رصيد دائن لصالحك' => $summary['credit'], 'متأخر حسب مواعيد السداد' => $summary['overdue']] as $label => $value)<div class="col-sm-6 col-lg-4"><div class="card h-100"><div class="card-body"><div class="text-muted">{{ $label }}</div><strong>{{ number_format($value, 2) }} ر.س</strong></div></div></div>@endforeach</div>
<p class="text-muted">يشمل الكشف الدفعات التي أكدها المصنع، بما فيها الدفعات المقدمة غير المخصصة لطلب. المسودات والعروض غير المؤكدة مستبعدة. إعادة منتج للمصنع لا تخصم مبلغًا تلقائيًا؛ تُراجع التسوية المالية مع خدمة العملاء.</p>
<div class="card"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>التاريخ</th><th>البيان</th><th>مدين</th><th>دائن</th><th>الرصيد</th></tr></thead><tbody>@forelse($entries as $entry)<tr><td>{{ $entry['date'] ?: 'بداية الحساب' }}</td><td>{{ $entry['reference'] }}</td><td>{{ number_format($entry['debit'], 2) }}</td><td>{{ number_format($entry['credit'], 2) }}</td><td>{{ number_format($entry['balance'], 2) }}</td></tr>@empty<tr><td colspan="5" class="text-center p-4">لا توجد حركات مالية.</td></tr>@endforelse</tbody></table></div></div><div class="mt-3">{{ $entries->links() }}</div>
@endsection
