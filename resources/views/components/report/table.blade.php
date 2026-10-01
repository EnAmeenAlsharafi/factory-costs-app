@props(['table', 'sticky' => true, 'caption' => null, 'primary' => [], 'mobileCards' => false])
@php
    $columns = collect($table->columns);
    if ($primary) {
        $columns = $columns->sortBy(fn ($column) => ($position = array_search($column->key, $primary, true)) === false ? count($primary) : $position)->values();
    }
    $coreColumns = $primary ? $columns->filter(fn ($column) => in_array($column->key, $primary, true)) : $columns;
    $secondaryColumns = $columns->filter(fn ($column) => $primary && ! in_array($column->key, $primary, true));
@endphp
<section {{ $attributes->class(['table-factory-wrapper report-table']) }} x-data="{ allColumns: false }" aria-label="{{ $caption ?? 'تفاصيل التقرير' }}">
    <div class="report-table-toolbar">
        <h2 class="report-section-title mb-0">{{ $caption ?? 'تفاصيل التقرير' }}</h2>
        @if ($secondaryColumns->isNotEmpty())
            <label class="report-no-print d-flex align-items-center gap-2 fs-7"><input type="checkbox" class="form-check-input m-0" x-model="allColumns"> إظهار جميع الأعمدة</label>
        @endif
    </div>
    @if ($mobileCards)
        <div class="report-mobile-records report-no-print">
            @forelse ($table->rows as $row)
                <article @class(['report-record', 'report-record-negative' => $row['is_negative'] ?? false])>
                    <dl class="report-record-grid">
                        @foreach ($coreColumns as $column)
                            <div><dt>{{ $column->label }}</dt><dd><x-report.cell :column="$column" :value="$row[$column->key] ?? null" /></dd></div>
                        @endforeach
                    </dl>
                    @if ($secondaryColumns->isNotEmpty())
                        <details><summary>تفاصيل إضافية</summary><dl class="report-record-grid mt-2">
                            @foreach ($secondaryColumns as $column)
                                <div><dt>{{ $column->label }}</dt><dd><x-report.cell :column="$column" :value="$row[$column->key] ?? null" /></dd></div>
                            @endforeach
                        </dl></details>
                    @endif
                </article>
            @empty
                <x-report.empty-state :message="$table->emptyMessage" />
            @endforelse
        </div>
    @endif
    <div @class(['table-responsive', 'report-desktop-table' => $mobileCards]) tabindex="0" role="region" aria-label="{{ $caption ?? 'جدول التقرير' }} — يمكن التمرير أفقياً">
        <table class="table table-factory table-hover align-middle mb-0 {{ $sticky ? 'table-sticky-first' : '' }}">
            <caption class="visually-hidden">{{ $caption ?? 'تفاصيل التقرير' }}</caption>
            <thead><tr>
                @foreach ($columns as $column)
                    <th scope="col" @class(['text-end' => $column->isNumeric(), 'report-secondary-col' => $primary && ! in_array($column->key, $primary, true)]) @if ($primary && ! in_array($column->key, $primary, true)) x-show="allColumns" @endif @if ($column->hint) title="{{ $column->hint }}" @endif>{{ $column->label }}</th>
                @endforeach
            </tr></thead>
            <tbody>
                @forelse ($table->rows as $row)
                    <tr @class(['report-row-alert' => $row['is_negative'] ?? false])>
                        @foreach ($columns as $column)
                            <td @class(['text-end text-nowrap' => $column->isNumeric(), 'report-secondary-col' => $primary && ! in_array($column->key, $primary, true)]) @if ($primary && ! in_array($column->key, $primary, true)) x-show="allColumns" @endif><x-report.cell :column="$column" :value="$row[$column->key] ?? null" /></td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ max(1, $columns->count()) }}"><x-report.empty-state :message="$table->emptyMessage" /></td></tr>
                @endforelse
            </tbody>
            @isset($footer)<tfoot>{{ $footer }}</tfoot>@endisset
        </table>
    </div>
    @if ($table->paginator && $table->paginator->hasPages())
        <div class="p-2 d-flex justify-content-center report-no-print">{{ $table->paginator->links() }}</div>
    @endif
    @if ($table->paginator)<div class="px-3 py-2 fs-8 text-muted border-top">إجمالي السجلات: {{ number_format($table->paginator->total()) }}</div>@endif
</section>
