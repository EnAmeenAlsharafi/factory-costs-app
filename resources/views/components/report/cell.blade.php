@props(['column', 'value' => null])
@if ($value === null || $value === '')
    <span class="text-muted">—</span>
@elseif ($column->type === \App\Domain\Reports\ReportColumn::LINK && is_array($value) && ! empty($value['url']))
    <a href="{{ $value['url'] }}" class="fw-semibold"><bdi>{{ $value['text'] }}</bdi></a>
@elseif ($column->type === \App\Domain\Reports\ReportColumn::BADGE && is_array($value))
    <span class="status-chip status-chip-{{ $value['tone'] ?? 'secondary' }}">{{ $value['label'] }}</span>
@elseif ($column->isNumeric())
    <bdi dir="ltr" @class(['report-number', 'text-danger fw-bold' => $column->highlightNegative && is_numeric($value) && $value < 0])>{{ $column->display($value) }}</bdi>
    @if ($column->highlightNegative && is_numeric($value) && $value < 0)<span class="visually-hidden">قيمة سالبة</span>@endif
@elseif (in_array($column->type, [\App\Domain\Reports\ReportColumn::CODE, \App\Domain\Reports\ReportColumn::DATE], true))
    <bdi dir="ltr" class="font-monospace fs-8 {{ str_contains($column->key, 'color') ? 'report-color-code' : '' }}">{{ $column->display($value) }}</bdi>
@else
    {{ $column->display($value) }}
@endif
