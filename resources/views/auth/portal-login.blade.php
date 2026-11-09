<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>دخول العملاء والمعارض — مصنع مفروشات سدير</title>
    <meta name="theme-color" content="#142d43">
    @include('partials.webapp-head', ['webAppVariant' => 'portal'])
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .portal-signin { min-height: 100svh; display: grid; place-items: center; padding: 24px 12px; }
        .portal-signin-card { width: 100%; max-width: 1040px; overflow: hidden; border-radius: 24px; }
        .portal-signin-brand { background: linear-gradient(145deg, #142d43, #285a72); color: #fff; }
        .portal-signin-icon { width: 56px; height: 56px; display: grid; place-items: center; background: #ffffff18; border-radius: 16px; }
        .portal-signin-form { max-width: 420px; margin-inline: auto; }
        .portal-signin .form-control, .portal-signin button { min-height: 48px; }
        /* 1366×768 laptops leave about 625px of browser height: keep the whole card on screen. */
        @media (min-width: 992px) and (max-height: 720px) {
            .portal-signin { padding-block: 12px; }
            .portal-signin section.p-lg-5 { padding: 1.5rem 3rem !important; }
        }
    </style>
</head>
<body>
<main class="portal-signin">
    <div class="portal-signin-card bg-white shadow-lg">
        <div class="row g-0">
            <section class="col-lg-5 portal-signin-brand p-4 p-lg-5 d-flex flex-column justify-content-between">
                <div>
                    <div class="portal-signin-icon mb-4"><i class="fas fa-store fs-4" aria-hidden="true"></i></div>
                    <div class="mb-2">مصنع مفروشات سدير</div>
                    <h1 class="h2 fw-bold mb-3">بوابة العملاء والمعارض</h1>
                    <p class="mb-0">من طلبك الأول إلى استلامه، تابع أعمالك مع المصنع من مكان واحد.</p>
                </div>
                <ul class="list-unstyled d-none d-lg-block mt-5 mb-0">
                    <li class="mb-3"><i class="fas fa-check-circle ms-2" aria-hidden="true"></i>ارفع طلبك وراجع عرض السعر</li>
                    <li class="mb-3"><i class="fas fa-check-circle ms-2" aria-hidden="true"></i>تابع التنفيذ ومواعيد التسليم</li>
                    <li><i class="fas fa-check-circle ms-2" aria-hidden="true"></i>اطّلع على دفعاتك وكشف حسابك</li>
                </ul>
            </section>
            <section class="col-lg-7 p-4 p-lg-5">
                <div class="portal-signin-form" x-data="{ showPassword: false }">
                    <h2 class="h4 fw-bold mb-2">تسجيل دخول العميل</h2>
                    <p class="text-muted mb-4">استخدم حساب المعرض أو عميل الجملة المسجل لدى المصنع.</p>
                    @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
                    @if($staffSession)
                        <div class="alert alert-info">يوجد حساب موظف مسجل على هذا المتصفح. سجّل الخروج منه أولًا للدخول بحساب العميل.</div>
                        <form method="POST" action="{{ route('portal.logout') }}">@csrf<button type="submit" class="btn btn-outline-primary w-100">تسجيل الخروج من الحساب الحالي</button></form>
                    @else
                        @include('partials.install-app', ['webAppVariant' => 'portal'])
                        <form method="POST" action="{{ route('portal.login.store') }}">
                            @csrf
                            <div class="mb-3"><label for="username" class="form-label">اسم المستخدم</label><input type="text" name="username" id="username" class="form-control @error('username') is-invalid @enderror" value="{{ old('username') }}" autocomplete="username" maxlength="100" dir="ltr" required autofocus></div>
                            <div class="mb-3"><label for="password" class="form-label">كلمة المرور</label><div class="input-group"><input type="password" :type="showPassword ? 'text' : 'password'" name="password" id="password" class="form-control" autocomplete="current-password" required><button type="button" class="btn btn-outline-secondary" @click="showPassword = !showPassword" :aria-label="showPassword ? 'إخفاء كلمة المرور' : 'عرض كلمة المرور'" :aria-pressed="showPassword.toString()"><i class="fas" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true"></i></button></div></div>
                            <div class="form-check mb-4"><input type="checkbox" class="form-check-input" name="remember" id="remember" value="1" @checked(old('remember'))><label for="remember" class="form-check-label">تذكرني على هذا الجهاز</label></div>
                            <button type="submit" class="btn btn-primary w-100 fw-bold">الدخول إلى بوابة العملاء</button>
                        </form>
                    @endif
                    <div class="border-top pt-4 mt-4"><h3 class="h6">تحتاج حسابًا أو نسيت بيانات الدخول؟</h3><p class="text-muted fs-7 mb-0">تواصل مع خدمة عملاء المصنع لتفعيل حساب منشأتك أو إعادة تعيين كلمة المرور.</p></div>
                    <p class="text-muted fs-8 mt-4 mb-0">&copy; {{ date('Y') }} مصنع مفروشات سدير</p>
                </div>
            </section>
        </div>
    </div>
</main>
</body>
</html>
