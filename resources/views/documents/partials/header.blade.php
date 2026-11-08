{{-- Company header: name and address on the right, logo in the centre, legal details aligned to the left edge. --}}
@php
    $logoSrc = ($forPdf ?? false) ? $company->logoFilePath() : $company->logo_url;
    $legal = collect([
        $company->commercial_register ? ['السجل التجاري', $company->commercial_register] : null,
        $company->vat_number ? ['الرقم الضريبي', $company->vat_number] : null,
        $company->phone ? ['الهاتف', $company->phone] : null,
        $company->email ? ['البريد', $company->email] : null,
    ])->filter();
    $place = collect([$company->city, $company->address])->filter()->implode('، ');
@endphp
    <table class="fo-head" width="100%">
        <tr>
            <td width="38%" style="text-align: right;">
                <div class="fo-name">{{ $company->name_ar }}</div>
                @if ($company->name_en)
                    <div class="fo-name-en">{{ $company->name_en }}</div>
                @endif
                @if ($place)
                    <div class="fo-place">{{ $place }}</div>
                @endif
            </td>
            <td width="24%" style="text-align: center;">
                @if ($logoSrc)
                    <img src="{{ $logoSrc }}" alt="{{ $company->name_ar }}" style="height: 62pt; width: auto; max-width: 140pt;">
                @endif
            </td>
            <td width="38%" style="text-align: left;">
                @if ($legal->isNotEmpty())
                    <table class="fo-legal" width="100%">
                        @foreach ($legal as [$key, $value])
                            <tr><td style="text-align: left;"><span style="color: #64748b;">{{ $key }}:</span> <span style="font-weight: bold; color: #0f172a;"><bdo dir="ltr">{{ $value }}</bdo></span></td></tr>
                        @endforeach
                    </table>
                @endif
            </td>
        </tr>
    </table>
    <div class="fo-band" style="margin: 10pt 0 12pt;"></div>

