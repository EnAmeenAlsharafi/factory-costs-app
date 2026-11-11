{{--
    Turns phone notifications on for this browser. Permission is asked only on the press, never on
    page load, because a prompt nobody asked for is dismissed once and blocked for good.
--}}
<div x-data="pushToggle" x-cloak class="d-flex flex-wrap align-items-center gap-2">
    <template x-if="state === 'off'">
        <button type="button" class="btn btn-outline-primary btn-sm" x-on:click="enable()">
            <i class="fas fa-mobile-screen-button ms-1" aria-hidden="true"></i> تفعيل تنبيهات الجوال
        </button>
    </template>

    <template x-if="state === 'on'">
        <span class="d-flex align-items-center gap-2">
            <span class="badge bg-success-subtle text-success-emphasis border">
                <i class="fas fa-circle-check ms-1" aria-hidden="true"></i> تنبيهات الجوال مفعّلة
            </span>
            <button type="button" class="btn btn-link btn-sm text-decoration-none text-secondary p-0" x-on:click="disable()">إيقافها</button>
        </span>
    </template>

    <template x-if="state === 'working'">
        <span class="text-muted fs-8"><i class="fas fa-spinner fa-spin ms-1" aria-hidden="true"></i> جارٍ…</span>
    </template>

    <template x-if="state === 'blocked'">
        <span class="text-muted fs-8">
            <i class="fas fa-bell-slash ms-1" aria-hidden="true"></i>
            التنبيهات محجوبة في إعدادات المتصفح لهذا الموقع.
        </span>
    </template>

    <template x-if="state === 'needs-install'">
        <span class="text-muted fs-8">
            <i class="fas fa-mobile-screen-button ms-1" aria-hidden="true"></i>
            لتصلك التنبيهات على الآيفون، أضف النظام إلى الشاشة الرئيسية ثم افتحه من هناك.
        </span>
    </template>

    <template x-if="state === 'unavailable'">
        <span class="text-muted fs-8">
            <i class="fas fa-bell-slash ms-1" aria-hidden="true"></i>
            تنبيهات الجوال غير مهيأة على الخادم بعد.
        </span>
    </template>

    <span x-show="message" x-text="message" class="text-danger fs-8"></span>
</div>
