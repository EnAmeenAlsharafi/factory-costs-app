@extends('layouts.portal')
@section('title', 'بيانات المنشأة')
@section('content')
<h1 class="h3 mb-4">بيانات المنشأة</h1><div class="card"><div class="card-body"><dl class="row mb-0">@foreach(['رقم العميل' => $customer->customer_code, 'اسم العميل' => $customer->name, 'الاسم التجاري' => $customer->commercial_name, 'الجوال' => $customer->mobile, 'البريد الإلكتروني' => $customer->email, 'المدينة' => $customer->city, 'العنوان' => $customer->address, 'الرقم الضريبي' => $customer->tax_number] as $label => $value)<dt class="col-sm-4 py-2">{{ $label }}</dt><dd class="col-sm-8 py-2">{{ $value ?: '—' }}</dd>@endforeach</dl></div></div><p class="text-muted mt-3">لتحديث بيانات المنشأة، تواصل مع خدمة العملاء.</p>
@endsection
