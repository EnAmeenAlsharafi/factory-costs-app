{{--
    Driver contact strip: call, WhatsApp (Saudi numbers normalised to 966…, message pre-filled)
    and navigation (a shared map link in the location notes wins over a search by address).
--}}
@php
    $rawPhone = preg_replace('/[^0-9+]/', '', (string) $delivery->customer_phone_snapshot);
    $digits = ltrim($rawPhone, '+');
    $waNumber = match (true) {
        $digits === '' => null,
        str_starts_with($digits, '00') => substr($digits, 2),
        str_starts_with($digits, '966') => $digits,
        str_starts_with($digits, '05') => '966'.substr($digits, 1),
        str_starts_with($digits, '5') && strlen($digits) === 9 => '966'.$digits,
        default => $digits,
    };
    $companyName = \App\Models\CompanySetting::current()->name_ar;
    $waText = 'السلام عليكم، معك مندوب التوصيل من '.$companyName.' بخصوص طلبكم رقم '.$delivery->delivery_number.'.';
    preg_match('#https?://\S+#u', (string) $delivery->location_notes, $sharedLink);
    $addressQuery = collect([$delivery->delivery_address_snapshot, $delivery->district_snapshot, $delivery->city_snapshot])->filter()->implode('، ');
    $mapUrl = $sharedLink[0] ?? ($addressQuery !== '' ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($addressQuery) : null);
@endphp
<div class="driver-contact" role="group" aria-label="التواصل مع العميل">
    @if ($rawPhone !== '')
        <a href="tel:{{ $rawPhone }}" class="driver-contact-btn is-call"><i class="fas fa-phone" aria-hidden="true"></i><span>اتصال</span></a>
        <a href="https://wa.me/{{ $waNumber }}?text={{ rawurlencode($waText) }}" target="_blank" rel="noopener" class="driver-contact-btn is-whatsapp"><i class="fab fa-whatsapp" aria-hidden="true"></i><span>واتساب</span></a>
    @else
        <span class="driver-contact-btn is-disabled" aria-disabled="true"><i class="fas fa-phone-slash" aria-hidden="true"></i><span>لا يوجد رقم</span></span>
    @endif
    @if ($mapUrl)
        <a href="{{ $mapUrl }}" target="_blank" rel="noopener" class="driver-contact-btn is-map"><i class="fas fa-location-arrow" aria-hidden="true"></i><span>الموقع</span></a>
    @else
        <span class="driver-contact-btn is-disabled" aria-disabled="true"><i class="fas fa-location-dot" aria-hidden="true"></i><span>لا يوجد عنوان</span></span>
    @endif
</div>
