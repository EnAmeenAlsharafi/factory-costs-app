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
    _scrollHandler: null,
    _resizeHandler: null,

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

        if (typeof this.$cleanup === 'function') {
            this.$cleanup(() => {
                if (this._scrollHandler) {
                    window.removeEventListener('scroll', this._scrollHandler, true);
                }
                if (this._resizeHandler) {
                    window.removeEventListener('resize', this._resizeHandler);
                }
            });
        }
    },

    destroy() {
        if (this._scrollHandler) {
            window.removeEventListener('scroll', this._scrollHandler, true);
        }
        if (this._resizeHandler) {
            window.removeEventListener('resize', this._resizeHandler);
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

        const minW = config.minWidth || 360;
        const width = Math.max(rect.width, minW);
        const vw = window.innerWidth;
        
        if (vw <= 480) {
            this.dropdownStyle = `position: fixed; top: ${Math.round(rect.bottom + 4)}px; left: 12px; right: 12px; width: auto; max-height: 260px; z-index: 1070;`;
            return;
        }

        let left = rect.right - width;
        if (left < 10) {
            left = 10;
        }
        if (left + width > vw - 10) {
            left = vw - width - 10;
        }

        this.dropdownStyle = `position: fixed; top: ${Math.round(rect.bottom + 4)}px; left: ${Math.round(left)}px; width: ${Math.round(width)}px; max-height: 280px; z-index: 1070;`;
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
            this.errorMessage = 'تعذر تحميل النتائج';
        });
    },

    onFocus() {
        this.checkDisabled();
        if (this.isDisabled) return;
        this.isOpen = true;
        this.updatePosition();
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
