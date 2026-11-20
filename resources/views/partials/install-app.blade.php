{{--
    Offer to install as an app on phones: one compact row (icon, title, a single action, close),
    with the browser-specific steps folded under «كيف؟». Hidden once installed, when opened as the
    app, or after closing it. $webAppVariant: 'system' or 'portal'.
--}}
@php $installTitle = ($webAppVariant ?? 'system') === 'portal' ? 'ثبّت بوابة العملاء كتطبيق على جوالك' : 'ثبّت النظام كتطبيق على جوالك'; @endphp
<section class="install-app" x-data="installApp" x-show="visible" x-cloak role="region" aria-label="تثبيت التطبيق">
    <div class="install-app-row">
        <div class="install-app-icon" aria-hidden="true"><img src="{{ route('webapp.icon', ['size' => 192, 'v' => app(\App\Http\Controllers\WebAppController::class)->iconVersion()] + (($webAppVariant ?? 'system') === 'portal' ? ['app' => 'portal'] : [])) }}" alt=""></div>
        <div class="install-app-body">
            <p class="install-app-title">{{ $installTitle }}</p>
            <p class="install-app-text">يفتح بملء الشاشة وتصلك الإشعارات على الجوال.</p>
        </div>
        <button type="button" class="btn btn-primary btn-sm fw-bold install-app-cta" x-show="mode === 'prompt'" @click="install()">تثبيت</button>
        <button type="button" class="btn btn-outline-primary btn-sm fw-bold install-app-cta" x-show="mode !== 'prompt'"
                @click="showSteps = !showSteps" :aria-expanded="showSteps.toString()" aria-controls="install-app-steps">كيف؟</button>
        <button type="button" class="btn btn-link btn-sm text-muted install-app-close" @click="dismiss()" aria-label="إخفاء اقتراح التثبيت" title="لاحقاً">
            <i class="fas fa-xmark" aria-hidden="true"></i>
        </button>
    </div>

    <div id="install-app-steps" class="install-app-steps" x-show="showSteps && mode !== 'prompt'" x-transition>
        <p class="install-app-text" x-show="mode === 'ios'">
            اضغط زر المشاركة <i class="fas fa-arrow-up-from-bracket" aria-hidden="true"></i><span class="visually-hidden">(المشاركة)</span>
            أسفل الشاشة، ثم اختر «إضافة إلى الشاشة الرئيسية».
        </p>
        <p class="install-app-text" x-show="mode === 'manual'">
            افتح قائمة المتصفح <i class="fas fa-ellipsis-vertical" aria-hidden="true"></i><span class="visually-hidden">(القائمة)</span>
            واختر «تثبيت التطبيق». إن ظهر «إضافة إلى الشاشة الرئيسية» فقط فاختره ثم «تثبيت» وليس «إنشاء اختصار».
        </p>
        <p class="install-app-text" x-show="mode === 'inapp'">فتحت الرابط من داخل تطبيق آخر مثل واتساب. افتحه في متصفح الجوال (Chrome أو Safari) لتثبيته.</p>
        <div class="d-flex flex-wrap gap-2 mt-2">
            <a x-show="canOpenInChrome && (mode === 'manual' || mode === 'inapp')" :href="chromeUrl" class="btn btn-primary btn-sm fw-bold">
                <i class="fab fa-chrome me-1" aria-hidden="true"></i> فتح في Chrome
            </a>
            <button type="button" class="btn btn-outline-primary btn-sm" x-show="mode === 'inapp'" @click="copyLink()">
                <i class="fas" :class="copied ? 'fa-check' : 'fa-copy'" aria-hidden="true"></i>
                <span x-text="copied ? 'نُسخ الرابط، الصقه في المتصفح' : 'نسخ الرابط'"></span>
            </button>
        </div>
    </div>
</section>
