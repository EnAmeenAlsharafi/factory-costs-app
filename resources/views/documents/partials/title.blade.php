{{-- Document title with its number box, then a row of facts. Expects $company, $title, $subtitle, $numberLabel, $number, $facts (list of [label, value html, sub html?]). --}}
@php $primary = $company->primary_color ?: '#1d4ed8'; @endphp
<table width="100%" style="margin-bottom: 10pt;">
    <tr>
        <td style="vertical-align: middle;">
            <div class="fo-title">{{ $title }}</div>
            <div style="font-size: 9pt; color: #475569;">{{ $subtitle }}</div>
        </td>
        <td width="150" style="vertical-align: middle;">
            <table width="100%"><tr><td class="fo-docno">
                <div style="font-size: 8pt; color: #64748b;">{{ $numberLabel }}</div>
                <div style="font-size: 11.5pt; font-weight: bold; color: {{ $primary }};"><bdo dir="ltr">{{ $number }}</bdo></div>
            </td></tr></table>
        </td>
    </tr>
</table>

<table class="fo-info" width="100%" style="margin-bottom: 12pt;">
    <tr>
        @foreach ($facts as $fact)
            <td>
                <div style="font-size: 8pt; color: #64748b; padding-bottom: 2pt;">{{ $fact[0] }}</div>
                <div style="font-size: 10.5pt; font-weight: bold; color: #0f172a;">{!! $fact[1] !!}</div>
                @if (! empty($fact[2]))
                    <div style="font-size: 8.5pt; color: #475569;">{!! $fact[2] !!}</div>
                @endif
            </td>
        @endforeach
    </tr>
</table>
