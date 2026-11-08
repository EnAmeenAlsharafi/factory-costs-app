@extends('layouts.app')

@section('title', 'إعدادات الشركة - '.$company->name_ar)
@section('page-title', 'إعدادات الشركة')

@section('content')
<div class="d-flex flex-column gap-3"
     x-data="{
         name: @js(old('name_ar', $company->name_ar)),
         nameEn: @js(old('name_en', $company->name_en)),
         primary: @js(old('primary_color', $company->primary_color)),
         secondary: @js(old('secondary_color', $company->secondary_color)),
         phone: @js(old('phone', $company->phone)),
         vat: @js(old('vat_number', $company->vat_number)),
         logo: @js($company->logo_url),
         removeLogo: false,
         pickLogo(event) {
             const file = event.target.files[0];
             if (file) { this.logo = URL.createObjectURL(file); this.removeLogo = false; }
         },
     }">
    <div class="page-header-card">
        <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">
            <i class="fas fa-building text-primary fs-5" aria-hidden="true"></i> إعدادات الشركة
        </h1>
        <p class="page-header-subtitle text-muted mb-0 fs-7">بيانات المصنع وشعاره وألوانه تظهر في رأس المستندات المطبوعة، مثل طلبات القماش وأوامر الشراء.</p>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger mb-0" role="alert">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <form method="POST" action="{{ route('settings.company.update') }}" enctype="multipart/form-data" class="d-flex flex-column gap-3">
                @csrf
                @method('PUT')

                <section class="card-factory p-3" aria-labelledby="identity-heading">
                    <h2 id="identity-heading" class="h6 fw-bold text-dark mb-3">هوية المصنع</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name_ar" class="form-label required">الاسم بالعربية</label>
                            <input id="name_ar" name="name_ar" class="form-control" x-model="name" required maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label for="name_en" class="form-label">الاسم بالإنجليزية</label>
                            <input id="name_en" name="name_en" class="form-control" dir="ltr" x-model="nameEn" maxlength="150">
                        </div>
                        <div class="col-12">
                            <label for="logo" class="form-label">الشعار</label>
                            <div class="d-flex flex-wrap align-items-center gap-3">
                                <div class="company-logo-slot">
                                    <template x-if="logo && !removeLogo"><img :src="logo" alt="شعار الشركة"></template>
                                    <template x-if="!logo || removeLogo"><i class="fas fa-image text-muted fs-3" aria-hidden="true"></i></template>
                                </div>
                                <div class="flex-grow-1">
                                    <input id="logo" type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp" @change="pickLogo($event)">
                                    <div class="form-text">PNG أو JPG أو WebP، حتى 2 ميجابايت. يُفضّل شعار بخلفية شفافة.</div>
                                    @if ($company->logo_path)
                                        <div class="form-check mt-1">
                                            <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo" x-model="removeLogo">
                                            <label class="form-check-label fs-7" for="remove_logo">حذف الشعار الحالي</label>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card-factory p-3" aria-labelledby="colors-heading">
                    <h2 id="colors-heading" class="h6 fw-bold text-dark mb-3">ألوان الشركة</h2>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="primary_color" class="form-label required">اللون الأساسي</label>
                            <div class="d-flex gap-2">
                                <input type="color" class="form-control form-control-color" x-model="primary" aria-label="اختيار اللون الأساسي">
                                <input id="primary_color" name="primary_color" class="form-control" dir="ltr" x-model="primary" pattern="#[0-9a-fA-F]{6}" required>
                            </div>
                            <div class="form-text">للعناوين وخطوط الجداول في المستندات.</div>
                        </div>
                        <div class="col-sm-6">
                            <label for="secondary_color" class="form-label required">اللون الثانوي</label>
                            <div class="d-flex gap-2">
                                <input type="color" class="form-control form-control-color" x-model="secondary" aria-label="اختيار اللون الثانوي">
                                <input id="secondary_color" name="secondary_color" class="form-control" dir="ltr" x-model="secondary" pattern="#[0-9a-fA-F]{6}" required>
                            </div>
                            <div class="form-text">للخط الفاصل والإبرازات.</div>
                        </div>
                    </div>
                </section>

                <section class="card-factory p-3" aria-labelledby="legal-heading">
                    <h2 id="legal-heading" class="h6 fw-bold text-dark mb-3">البيانات النظامية والتواصل</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="commercial_register" class="form-label">رقم السجل التجاري</label>
                            <input id="commercial_register" name="commercial_register" class="form-control" dir="ltr" value="{{ old('commercial_register', $company->commercial_register) }}" maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label for="vat_number" class="form-label">الرقم الضريبي</label>
                            <input id="vat_number" name="vat_number" class="form-control" dir="ltr" x-model="vat" maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label">الهاتف</label>
                            <input id="phone" name="phone" class="form-control" dir="ltr" x-model="phone" maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">البريد الإلكتروني</label>
                            <input id="email" type="email" name="email" class="form-control" dir="ltr" value="{{ old('email', $company->email) }}" maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label for="website" class="form-label">الموقع الإلكتروني</label>
                            <input id="website" name="website" class="form-control" dir="ltr" value="{{ old('website', $company->website) }}" maxlength="150">
                        </div>
                        <div class="col-md-6">
                            <label for="city" class="form-label">المدينة</label>
                            <input id="city" name="city" class="form-control" value="{{ old('city', $company->city) }}" maxlength="100">
                        </div>
                        <div class="col-12">
                            <label for="address" class="form-label">العنوان</label>
                            <input id="address" name="address" class="form-control" value="{{ old('address', $company->address) }}" maxlength="255">
                        </div>
                        <div class="col-12">
                            <label for="document_footer" class="form-label">نص تذييل المستندات</label>
                            <textarea id="document_footer" name="document_footer" class="form-control" rows="2" maxlength="500" placeholder="مثال: يرجى ذكر رقم الطلب في فاتورة التوريد.">{{ old('document_footer', $company->document_footer) }}</textarea>
                        </div>
                    </div>
                </section>

                <x-mobile-action-bar class="action-bar-sticky">
                    <button type="submit" class="btn btn-primary fw-bold"><i class="fas fa-save me-1" aria-hidden="true"></i> حفظ الإعدادات</button>
                </x-mobile-action-bar>
            </form>
        </div>

        <div class="col-lg-5">
            <div class="company-preview-sticky">
                <h2 class="h6 fw-bold text-dark mb-2">معاينة رأس المستند</h2>
                <div class="card-factory p-3">
                    <div class="doc-head" :style="`--doc-primary: ${primary}; --doc-secondary: ${secondary}`">
                        <div class="doc-head-brand">
                            <template x-if="logo && !removeLogo"><img :src="logo" alt="" class="doc-head-logo"></template>
                            <div>
                                <div class="doc-head-name" x-text="name || 'اسم المصنع'"></div>
                                <div class="doc-head-name-en" dir="ltr" x-text="nameEn"></div>
                            </div>
                        </div>
                        <div class="doc-head-meta">
                            <div x-show="vat">الرقم الضريبي: <bdi x-text="vat"></bdi></div>
                            <div x-show="phone">هاتف: <bdi x-text="phone"></bdi></div>
                        </div>
                    </div>
                    <div class="doc-head-title" :style="`color: ${primary}`">طلب توريد قماش</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
