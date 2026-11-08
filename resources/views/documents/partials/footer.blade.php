{{-- Signature boxes and footer. Expects $preparedBy (name or null). --}}
    <table class="fo-sign" width="100%" style="margin-top: 40pt;">
        <tr>
            <td class="line">أعدّه: {{ $preparedBy }}</td>
            <td class="gap"></td>
            <td class="line">اعتمده</td>
            <td class="gap"></td>
            <td class="line">توقيع المورد</td>
        </tr>
    </table>

    <div class="fo-foot" style="margin-top: 16pt;">
        {{ $company->document_footer ?: 'يرجى ذكر رقم الطلب في فاتورة التوريد وإشعار التسليم.' }}
        @if ($company->website)
            <div style="direction: ltr;">{{ $company->website }}</div>
        @endif
    </div>
