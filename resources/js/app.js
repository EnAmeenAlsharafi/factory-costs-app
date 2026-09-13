import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('appShell', () => ({
    sidebarCollapsed: window.localStorage.getItem('sadir-sidebar-collapsed') === 'true',
    mobileSidebarOpen: false,
    openSections: JSON.parse(window.localStorage.getItem('sadir-open-sections') || '{}'),

    init() {
        this.$watch('sidebarCollapsed', (value) => {
            window.localStorage.setItem('sadir-sidebar-collapsed', String(value));
        });
        this.$watch('mobileSidebarOpen', (value) => {
            document.body.classList.toggle('shell-drawer-open', value);
        });
    },

    isSectionOpen(key, defaultActive = false) {
        if (this.openSections[key] !== undefined) {
            return this.openSections[key];
        }
        return defaultActive;
    },

    toggleSection(key, defaultActive = false) {
        const currentlyOpen = this.isSectionOpen(key, defaultActive);
        this.openSections[key] = !currentlyOpen;
        window.localStorage.setItem('sadir-open-sections', JSON.stringify(this.openSections));
    },

    openMobileSidebar() {
        this.mobileSidebarOpen = true;
        this.$nextTick(() => document.querySelector('#app-sidebar a, #app-sidebar button')?.focus());
    },

    closeMobileSidebar() {
        this.mobileSidebarOpen = false;
        this.$nextTick(() => document.getElementById('mobile-menu-toggle')?.focus());
    },
}));

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.table-responsive').forEach((wrapper, index) => {
        wrapper.setAttribute('tabindex', '0');
        wrapper.setAttribute('role', 'region');
        wrapper.setAttribute('aria-label', wrapper.getAttribute('aria-label') || `جدول بيانات قابل للتمرير ${index + 1}`);

        if (!wrapper.previousElementSibling?.classList.contains('table-scroll-hint')) {
            const hint = document.createElement('div');
            hint.className = 'table-scroll-hint';
            hint.innerHTML = '<i class="fa-solid fa-arrows-left-right" aria-hidden="true"></i> مرّر أفقيًا لعرض بقية الأعمدة';
            wrapper.before(hint);
        }
    });

    document.querySelectorAll('td .d-inline-flex').forEach((actions) => actions.classList.add('table-actions'));
    document.querySelectorAll('input[placeholder]:not([aria-label])').forEach((input) => input.setAttribute('aria-label', input.placeholder));
    document.querySelectorAll('select:not([aria-label])').forEach((select) => {
        const label = document.querySelector(`label[for="${select.id}"]`)?.textContent.trim()
            || select.options[0]?.textContent.trim()
            || 'اختيار قيمة';
        select.setAttribute('aria-label', label);
    });
    document.querySelectorAll('[title]:not([aria-label])').forEach((element) => element.setAttribute('aria-label', element.title));
});

Alpine.start();
