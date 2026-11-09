{{--
    Offer to install as an app on phones, with steps that fit the browser in hand. Hidden once
    installed, when opened as the app, or after «لاحقاً». $webAppVariant: 'system' or 'portal'.
--}}
@php $installTitle = ($webAppVariant ?? 'system') === 'portal' ? 'ثبّت بوابة العملاء كتطبيق على جوالك' : 'ثبّت النظام كتطبيق على جوالك'; @endphp
<section class="install-app" x-data="installApp" x-show="visible" x-cloak role="region" aria-label="تثبيت التطبيق">
    <div class="install-app-icon" aria-hidden="true"><img src="{{ route('webapp.icon', ['size' => 192, 'v' => app(\App\Http\Controllers\WebAppController::class)->iconVersion()]) }}" alt=""></div>
    <div class="install-app-body">
        <p class="install-app-title">{{ $installTitle }}</p>

        <p class="install-app-text" x-show="mode === 'prompt'">يفتح بملء الشاشة من أيقونة على الشاشة الرئيسية، دون شريط المتصفح.</p>

        <p class="install-app-text" x-show="mode === 'ios'">
            اضغط زر المشاركة <i class="fas fa-arrow-up-from-bracket" aria-hidden="true"></i><span class="visually-hidden">(المشاركة)</span>
            أسفل الشاشة، ثم اختر «إضافة إلى الشاشة الرئيسية».
        </p>

        <p class="install-app-text" x-show="mode === 'manual'">
            افتح قائمة المتصفح <i class="fas fa-ellipsis-vertical" aria-hidden="true"></i><span class="visually-hidden">(القائمة)</span>
            واختر «تثبيت التطبيق» أو «إضافة إلى الشاشة الرئيسية».
        </p>

        <div x-show="mode === 'inapp'">
            <p class="install-app-text">فتحت الرابط من داخل تطبيق آخر مثل واتساب. لتثبيت التطبيق افتح الرابط في متصفح الجوال (Chrome أو Safari).</p>
            <button type="button" class="btn btn-outline-primary btn-sm mt-1" @click="copyLink()">
                <i class="fas" :class="copied ? 'fa-check' : 'fa-copy'" aria-hidden="true"></i>
                <span x-text="copied ? 'تم نسخ الرابط، الصقه في المتصفح' : 'نسخ الرابط'"></span>
            </button>
        </div>
    </div>
    <div class="install-app-actions">
        <button type="button" class="btn btn-primary btn-sm fw-bold" x-show="mode === 'prompt'" @click="install()">تثبيت</button>
        <button type="button" class="btn btn-link btn-sm text-muted" @click="dismiss()">لاحقاً</button>
    </div>
</section>
