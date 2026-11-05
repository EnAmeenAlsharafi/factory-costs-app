@extends('layouts.portal')
@section('title', 'بيانات المنشأة')
@section('content')
<h1 class="h4 fw-bold mb-3">بيانات المنشأة</h1>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <dl class="portal-facts mb-0">
            <div><dt>رقم العميل</dt><dd><span class="ltr-isolate">{{ $customer->customer_code ?: '—' }}</span></dd></div>
            <div><dt>اسم العميل</dt><dd>{{ $customer->name ?: '—' }}</dd></div>
            <div><dt>الاسم التجاري</dt><dd>{{ $customer->commercial_name ?: '—' }}</dd></div>
            <div><dt>الجوال</dt><dd>
                @if($customer->mobile)
                    <a class="text-reset" href="tel:{{ $customer->mobile }}"><span class="ltr-isolate">{{ $customer->mobile }}</span></a>
                @else — @endif
            </dd></div>
            <div><dt>البريد الإلكتروني</dt><dd>
                @if($customer->email)
                    <a class="text-reset" href="mailto:{{ $customer->email }}"><span class="ltr-isolate">{{ $customer->email }}</span></a>
                @else — @endif
            </dd></div>
            <div><dt>المدينة</dt><dd>{{ $customer->city ?: '—' }}</dd></div>
            <div><dt>الرقم الضريبي</dt><dd><span class="ltr-isolate">{{ $customer->tax_number ?: '—' }}</span></dd></div>
        </dl>
        <hr class="my-3">
        <dl class="mb-0">
            <dt class="text-muted fs-7 fw-semibold">العنوان</dt>
            <dd class="mb-0 fw-bold">{{ $customer->address ?: '—' }}</dd>
        </dl>
    </div>
</div>

<p class="text-muted fs-7 mt-3">لتحديث بيانات المنشأة، تواصل مع خدمة العملاء.</p>
@endsection
