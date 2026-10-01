import 'bootstrap/dist/js/bootstrap.bundle.min.js';
import Alpine from 'alpinejs';
import { registerReportFilters } from './report-ui';

window.Alpine = Alpine;
registerReportFilters(Alpine);

const MOBILE_QUERY = window.matchMedia('(max-width: 767.98px)');
const isMobileViewport = () => MOBILE_QUERY.matches;

function readStoredJson(key, fallback) {
    try {
        return JSON.parse(window.localStorage.getItem(key) || '') ?? fallback;
    } catch (e) {
        return fallback;
    }
}

function writeStored(key, value) {
    try {
        window.localStorage.setItem(key, value);
    } catch (e) {
        // Storage may be unavailable (private mode); navigation still works without persistence.
    }
}

Alpine.data('appShell', () => ({
    sidebarCollapsed: readStoredJson('sadir-sidebar-collapsed', false) === true,
    mobileSidebarOpen: false,
    openSections: readStoredJson('sadir-open-sections', {}),
    // On phones the drawer starts with only the current module expanded; toggles last for this page only.
    mobileSections: {},
    isMobile: isMobileViewport(),

    init() {
        this.$watch('sidebarCollapsed', (value) => {
            writeStored('sadir-sidebar-collapsed', String(value));
        });
        this.$watch('mobileSidebarOpen', (value) => {
            document.body.classList.toggle('shell-drawer-open', value);
            const main = document.querySelector('.app-main-column');
            if (main) {
                main.toggleAttribute('inert', value);
            }
        });
        MOBILE_QUERY.addEventListener('change', (e) => {
            this.isMobile = e.matches;
            if (!e.matches && this.mobileSidebarOpen) {
                this.mobileSidebarOpen = false;
            }
        });
        // Close the drawer immediately when a destination is tapped (the page then navigates).
        document.getElementById('app-sidebar')?.addEventListener('click', (e) => {
            if (this.mobileSidebarOpen && e.target.closest('a[href]')) {
                this.mobileSidebarOpen = false;
            }
        });
    },

    isSectionOpen(key, defaultActive = false) {
        if (this.isMobile) {
            return this.mobileSections[key] ?? defaultActive;
        }
        if (this.openSections[key] !== undefined) {
            return this.openSections[key];
        }
        return defaultActive;
    },

    toggleSection(key, defaultActive = false) {
        const currentlyOpen = this.isSectionOpen(key, defaultActive);
        if (this.isMobile) {
            this.mobileSections[key] = !currentlyOpen;
            return;
        }
        this.openSections[key] = !currentlyOpen;
        writeStored('sadir-open-sections', JSON.stringify(this.openSections));
    },

    openMobileSidebar() {
        this.mobileSidebarOpen = true;
        this.$nextTick(() => {
            const active = document.querySelector('#app-sidebar .sidebar-nav-link.active');
            active?.scrollIntoView({ block: 'center' });
            document.querySelector('#app-sidebar button, #app-sidebar a')?.focus();
        });
    },

    closeMobileSidebar() {
        this.mobileSidebarOpen = false;
        this.$nextTick(() => document.getElementById('mobile-menu-toggle')?.focus());
    },
}));

let typeaheadInstanceCounter = 0;

Alpine.data('typeaheadSelect', (config = {}) => ({
    rootEl: null,
    name: config.name || '',
    endpoint: config.endpoint || '',
    placeholder: config.placeholder || 'ابحث...',
    selectedId: config.initialId || '',
    selectedLabel: config.initialLabel || '',
    searchQuery: config.initialLabel || '',
    results: [],
    loading: false,
    isOpen: false,
    highlightedIndex: -1,
    hasSupplierFabrics: true,
    hasSearched: false,
    disabledPlaceholder: config.disabledPlaceholder || '',
    errorMessage: '',
    requestId: 0,
    dropdownStyle: '',
    isDisabled: false,
    dropUp: false,
    uid: `ta-${++typeaheadInstanceCounter}`,
    _scrollHandler: null,
    _resizeHandler: null,

    get listboxId() {
        return `${this.uid}-listbox`;
    },

    get activeOptionId() {
        return this.highlightedIndex >= 0 ? `${this.uid}-opt-${this.highlightedIndex}` : '';
    },

    optionId(index) {
        return `${this.uid}-opt-${index}`;
    },

    get placeholderText() {
        if (this.isDisabled && this.disabledPlaceholder) {
            return this.disabledPlaceholder;
        }
        return this.placeholder;
    },

    init() {
        this.rootEl = this.$el;
        this.checkDisabled();

        if (this.selectedId && !this.selectedLabel) {
            this.fetchResults('', true);
        }

        // Automatic synchronization with parent when requireParent is true
        if (config.requireParent) {
            const container = (this.rootEl || this.$el)?.closest('tr') || (this.rootEl || this.$el)?.closest('.fabric-spec-box') || document;
            
            const handleParentSelect = (e) => {
                const target = e.target;
                const srcContainer = target ? target.closest('.typeahead-container') : null;
                if (srcContainer && srcContainer !== (this.rootEl || this.$el) && srcContainer.classList.contains('supplier-wrapper')) {
                    this.clearSelection(false);
                    this.isDisabled = false;
                    this.fetchResults('');
                }
            };

            const handleParentClear = (e) => {
                const target = e.target;
                const srcContainer = target ? target.closest('.typeahead-container') : null;
                if (srcContainer && srcContainer !== (this.rootEl || this.$el) && srcContainer.classList.contains('supplier-wrapper')) {
                    this.clearSelection(false);
                    this.isDisabled = true;
                    this.results = [];
                    this.isOpen = false;
                }
            };

            container.addEventListener('typeahead-select', handleParentSelect);
            container.addEventListener('typeahead-clear', handleParentClear);

            if (typeof this.$cleanup === 'function') {
                this.$cleanup(() => {
                    container.removeEventListener('typeahead-select', handleParentSelect);
                    container.removeEventListener('typeahead-clear', handleParentClear);
                });
            }
        }

        this._scrollHandler = () => {
            if (this.isOpen) {
                this.updatePosition();
            }
        };
        this._resizeHandler = () => {
            if (this.isOpen) {
                this.updatePosition();
            }
        };

        window.addEventListener('scroll', this._scrollHandler, true);
        window.addEventListener('resize', this._resizeHandler);
        // The on-screen keyboard shrinks the visual viewport without a window resize on iOS.
        window.visualViewport?.addEventListener('resize', this._resizeHandler);

        if (typeof this.$cleanup === 'function') {
            this.$cleanup(() => this.destroy());
        }
    },

    destroy() {
        if (this._scrollHandler) {
            window.removeEventListener('scroll', this._scrollHandler, true);
        }
        if (this._resizeHandler) {
            window.removeEventListener('resize', this._resizeHandler);
            window.visualViewport?.removeEventListener('resize', this._resizeHandler);
        }
    },

    getParentId() {
        if (typeof config.getParentId === 'function') {
            try {
                const pid = config.getParentId();
                if (pid) return pid;
            } catch (e) {
                // Ignore closure scope issues
            }
        }
        const el = this.rootEl || this.$el;
        if (config.parentSelector && el) {
            const row = el.closest('tr') || el.closest('.fabric-spec-box') || document;
            const parentInp = row.querySelector(config.parentSelector);
            if (parentInp && parentInp.value) {
                return parentInp.value;
            }
        }
        return null;
    },

    checkDisabled() {
        if (config.requireParent) {
            const parentId = this.getParentId();
            this.isDisabled = !parentId;
        } else {
            this.isDisabled = false;
        }
        return this.isDisabled;
    },

    updatePosition() {
        if (!this.$refs.inputBox) return;
        const rect = this.$refs.inputBox.getBoundingClientRect();
        
        if (rect.width === 0 && rect.height === 0) {
            this.isOpen = false;
            return;
        }

        const vw = window.innerWidth;
        // Visible height excludes the on-screen keyboard where the browser exposes it.
        const viewport = window.visualViewport;
        const visibleTop = viewport ? viewport.offsetTop : 0;
        const visibleBottom = viewport ? viewport.offsetTop + viewport.height : window.innerHeight;
        const spaceBelow = visibleBottom - rect.bottom - 8;
        const spaceAbove = rect.top - visibleTop - 8;
        const preferred = vw <= 575 ? 300 : 280;

        this.dropUp = spaceBelow < 180 && spaceAbove > spaceBelow;
        const available = Math.max(140, Math.min(preferred, this.dropUp ? spaceAbove : spaceBelow));
        const vertical = this.dropUp
            ? `bottom: ${Math.round(window.innerHeight - rect.top + 4)}px;`
            : `top: ${Math.round(rect.bottom + 4)}px;`;

        if (vw <= 575) {
            // Phones: nearly full-width results so labels are never clipped.
            this.dropdownStyle = `position: fixed; ${vertical} left: 8px; right: 8px; width: auto; max-height: ${Math.round(available)}px; z-index: 1070;`;
            return;
        }

        const minW = Math.min(config.minWidth || 360, vw - 20);
        const width = Math.max(rect.width, minW);
        let left = rect.right - width;
        if (left < 10) {
            left = 10;
        }
        if (left + width > vw - 10) {
            left = vw - width - 10;
        }

        this.dropdownStyle = `position: fixed; ${vertical} left: ${Math.round(left)}px; width: ${Math.round(width)}px; max-height: ${Math.round(available)}px; z-index: 1070;`;
    },

    fetchResults(query = null, selectFirstIfMatchingId = false) {
        this.checkDisabled();
        if (this.isDisabled) {
            this.results = [];
            this.loading = false;
            return;
        }

        const q = query !== null ? query : this.searchQuery;
        let url = `${this.endpoint}?q=${encodeURIComponent(q || '')}`;
        
        const parentId = this.getParentId();
        if (config.parentParam && parentId) {
            url += `&${config.parentParam}=${encodeURIComponent(parentId)}`;
        }

        const currentRequestId = ++this.requestId;
        this.loading = true;
        this.errorMessage = '';

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => {
            if (res.headers.has('X-Supplier-Has-Fabrics')) {
                this.hasSupplierFabrics = res.headers.get('X-Supplier-Has-Fabrics') === '1';
            } else {
                this.hasSupplierFabrics = true;
            }

            if (!res.ok) {
                throw new Error('Server error');
            }
            return res.json();
        })
        .then(data => {
            if (currentRequestId !== this.requestId) {
                return;
            }
            this.results = data || [];
            this.loading = false;
            this.hasSearched = true;
            this.highlightedIndex = -1;
            
            if (selectFirstIfMatchingId && this.selectedId) {
                const found = this.results.find(r => r.id == this.selectedId);
                if (found) {
                    this.selectedLabel = found.label;
                    this.searchQuery = found.label;
                }
            }
            this.$nextTick(() => this.updatePosition());
        })
        .catch(() => {
            if (currentRequestId !== this.requestId) return;
            this.results = [];
            this.loading = false;
            this.hasSearched = true;
            // Never present a failed request as "no results".
            this.errorMessage = navigator.onLine === false
                ? 'لا يوجد اتصال بالإنترنت. تحقق من الشبكة ثم أعد المحاولة.'
                : 'تعذر تحميل النتائج. أعد المحاولة.';
        });
    },

    retry() {
        this.isOpen = true;
        this.fetchResults();
    },

    onFocus() {
        this.checkDisabled();
        if (this.isDisabled) return;
        this.isOpen = true;
        this.updatePosition();
        if (isMobileViewport() && this.$refs.inputBox) {
            // Bring the field into the upper part of the screen so results stay above the keyboard.
            window.setTimeout(() => {
                this.$refs.inputBox?.scrollIntoView({ block: 'start', behavior: 'smooth' });
                window.setTimeout(() => this.isOpen && this.updatePosition(), 350);
            }, 250);
        }
        if (this.results.length === 0 || this.searchQuery) {
            this.fetchResults();
        }
    },

    onInput() {
        this.checkDisabled();
        if (this.isDisabled) return;
        this.isOpen = true;
        this.selectedId = '';
        this.selectedLabel = '';
        this.updatePosition();
        this.fetchResults();
        
        const el = this.rootEl || this.$el;
        const hiddenInp = el ? el.querySelector('input[type="hidden"]') : null;
        if (hiddenInp) {
            hiddenInp.value = '';
            hiddenInp.dispatchEvent(new Event('input', { bubbles: true }));
            hiddenInp.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (el) {
            el.dispatchEvent(new CustomEvent('typeahead-clear', {
                bubbles: true,
                detail: null
            }));
        }

        if (config.onChange) {
            try {
                config.onChange(null);
            } catch (e) {
                console.error('typeahead onInput onChange error', e);
            }
        }
    },

    selectItem(item) {
        this.selectedId = item.id;
        this.selectedLabel = item.label;
        this.searchQuery = item.label;
        this.isOpen = false;
        this.results = [];
        this.highlightedIndex = -1;

        const el = this.rootEl || this.$el;
        const hiddenInp = el ? el.querySelector('input[type="hidden"]') : null;
        if (hiddenInp) {
            hiddenInp.value = item.id;
            hiddenInp.dispatchEvent(new Event('input', { bubbles: true }));
            hiddenInp.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (el) {
            el.dispatchEvent(new CustomEvent('typeahead-select', {
                bubbles: true,
                detail: { item, id: item.id, label: item.label, code: item.code }
            }));
        }

        if (config.onChange) {
            try {
                config.onChange(item);
            } catch (e) {
                console.error('typeahead selectItem onChange error', e);
            }
        }
    },

    clearSelection(dispatchClear = true) {
        this.selectedId = '';
        this.selectedLabel = '';
        this.searchQuery = '';
        this.results = [];
        this.isOpen = false;
        this.highlightedIndex = -1;

        const el = this.rootEl || this.$el;
        const hiddenInp = el ? el.querySelector('input[type="hidden"]') : null;
        if (hiddenInp) {
            hiddenInp.value = '';
            hiddenInp.dispatchEvent(new Event('input', { bubbles: true }));
            hiddenInp.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (dispatchClear && el) {
            el.dispatchEvent(new CustomEvent('typeahead-clear', {
                bubbles: true,
                detail: null
            }));
        }

        if (config.onChange) {
            try {
                config.onChange(null);
            } catch (e) {
                console.error('typeahead clearSelection onChange error', e);
            }
        }
    },

    navigateDown() {
        if (!this.isOpen) {
            this.onFocus();
            return;
        }
        if (this.highlightedIndex < this.results.length - 1) {
            this.highlightedIndex++;
        }
    },

    navigateUp() {
        if (this.highlightedIndex > 0) {
            this.highlightedIndex--;
        }
    },

    selectHighlighted() {
        if (this.highlightedIndex >= 0 && this.highlightedIndex < this.results.length) {
            this.selectItem(this.results[this.highlightedIndex]);
        }
    },

    closeDropdown() {
        this.isOpen = false;
        if (!this.selectedId) {
            this.searchQuery = '';
        } else if (this.selectedId && this.selectedLabel) {
            this.searchQuery = this.selectedLabel;
        }
    }
}));

/**
 * Global form safety used by operational screens (desktop and mobile alike):
 *  - data-confirm="..." on a <form> or its submit button asks before irreversible actions.
 *  - Every submitted form disables its submitter to prevent duplicate posts on slow networks.
 *  - data-unsaved-warning on a <form> warns before leaving with unsaved edits.
 */
function initFormSafety() {
    let navigatingViaSubmit = false;

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || event.defaultPrevented) {
            return;
        }
        const submitter = event.submitter || null;
        const message = submitter?.dataset.confirm || form.dataset.confirm;

        if (message && !window.confirm(message)) {
            event.preventDefault();
            return;
        }

        if (form.method.toLowerCase() === 'get' || form.target === '_blank') {
            return;
        }

        navigatingViaSubmit = true;
        // Disable after the browser has captured the form data (the submitter's name/value is still sent).
        window.setTimeout(() => {
            const buttons = submitter ? [submitter] : form.querySelectorAll('button[type="submit"], button:not([type])');
            buttons.forEach((button) => {
                button.disabled = true;
                button.classList.add('is-submitting');
                button.setAttribute('aria-busy', 'true');
            });
        }, 0);
    });

    // Re-enable buttons when a page is restored from the back/forward cache.
    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) {
            return;
        }
        navigatingViaSubmit = false;
        document.querySelectorAll('button.is-submitting').forEach((button) => {
            button.disabled = false;
            button.classList.remove('is-submitting');
            button.removeAttribute('aria-busy');
        });
    });

    const guardedForms = document.querySelectorAll('form[data-unsaved-warning]');
    if (guardedForms.length === 0) {
        return;
    }
    let isDirty = false;
    guardedForms.forEach((form) => {
        form.addEventListener('input', () => { isDirty = true; });
        form.addEventListener('change', () => { isDirty = true; });
    });
    window.addEventListener('beforeunload', (event) => {
        if (isDirty && !navigatingViaSubmit) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
}

/** Clear feedback when the phone loses or regains connectivity. */
function initNetworkBanner() {
    let banner = null;
    let hideTimer = null;

    const show = (text, online) => {
        if (!banner) {
            banner = document.createElement('div');
            banner.className = 'network-banner';
            banner.setAttribute('role', 'status');
            banner.setAttribute('aria-live', 'polite');
            document.body.appendChild(banner);
        }
        window.clearTimeout(hideTimer);
        banner.textContent = text;
        banner.classList.toggle('is-online', online);
        banner.hidden = false;
        if (online) {
            hideTimer = window.setTimeout(() => { banner.hidden = true; }, 3000);
        }
    };

    window.addEventListener('offline', () => show('انقطع الاتصال بالإنترنت. لن يتم حفظ أي إجراء حتى يعود الاتصال.', false));
    window.addEventListener('online', () => show('عاد الاتصال بالإنترنت.', true));
}

/**
 * Hide sticky bottom action bars while the on-screen keyboard is open so they never cover the focused field.
 */
function initKeyboardAwareness() {
    const textEntry = 'input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]):not([type="submit"]):not([type="button"]), textarea';
    document.addEventListener('focusin', (event) => {
        if (isMobileViewport() && event.target.matches?.(textEntry)) {
            document.body.classList.add('keyboard-open');
        }
    });
    document.addEventListener('focusout', () => {
        window.setTimeout(() => {
            if (!document.activeElement?.matches?.(textEntry)) {
                document.body.classList.remove('keyboard-open');
            }
        }, 50);
    });
}

/**
 * Chrome renders <input type="number"> in Arabic-Indic digits on lang="ar" pages, which mixes digit systems
 * with the rest of the UI. Quantities, prices and dimensions stay in Western digits everywhere.
 */
function normalizeNumericInputs(root = document) {
    root.querySelectorAll?.('input[type="number"]:not([lang])').forEach((input) => input.setAttribute('lang', 'en'));
}

function refreshScrollHints() {
    document.querySelectorAll('.table-responsive').forEach((wrapper) => {
        const hint = wrapper.previousElementSibling;
        if (hint?.classList.contains('table-scroll-hint')) {
            hint.classList.toggle('is-overflowing', wrapper.scrollWidth > wrapper.clientWidth + 2);
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initFormSafety();
    initNetworkBanner();
    initKeyboardAwareness();
    normalizeNumericInputs();
    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
            if (node.nodeType === Node.ELEMENT_NODE) {
                normalizeNumericInputs(node.matches?.('input[type="number"]') ? node.parentElement : node);
            }
        }));
    }).observe(document.body, { childList: true, subtree: true });

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
    refreshScrollHints();
    window.addEventListener('resize', refreshScrollHints);

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
