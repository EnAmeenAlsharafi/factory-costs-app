{{-- Offer to install the system as an app on phones. Hidden once installed, when opened as the app, or after «لاحقاً». --}}
<section class="install-app" x-data="installApp" x-show="visible" x-cloak role="region" aria-label="تثبيت التطبيق">
    <div class="install-app-icon" aria-hidden="true"><img src="{{ route('webapp.icon', ['size' => 192, 'v' => \App\Models\CompanySetting::current()->updated_at?->timestamp]) }}" alt=""></div>
    <div class="install-app-body">
        <p class="install-app-title">ثبّت النظام كتطبيق على جوالك</p>
        <p class="install-app-text" x-show="!ios">يفتح بملء الشاشة من أيقونة على الشاشة الرئيسية، دون شريط المتصفح.</p>
        <p class="install-app-text" x-show="ios">
            اضغط زر المشاركة <i class="fas fa-arrow-up-from-bracket" aria-hidden="true"></i><span class="visually-hidden">(المشاركة)</span>
            ثم «إضافة إلى الشاشة الرئيسية».
        </p>
    </div>
    <div class="install-app-actions">
        <button type="button" class="btn btn-primary btn-sm fw-bold" x-show="canPrompt" @click="install()">تثبيت</button>
        <button type="button" class="btn btn-link btn-sm text-muted" @click="dismiss()">لاحقاً</button>
    </div>
</section>
