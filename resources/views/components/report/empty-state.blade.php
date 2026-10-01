@props(['message', 'hint' => 'جرّب تغيير الفترة أو مسح الفلاتر لعرض نطاق أوسع.'])
<div {{ $attributes->class(['report-empty']) }} role="status">
    <i class="far fa-folder-open" aria-hidden="true"></i>
    <p class="fw-semibold mb-1">{{ $message }}</p>
    @if ($hint)<p class="text-muted fs-7 mb-0">{{ $hint }}</p>@endif
</div>
