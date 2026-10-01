export function registerReportFilters(Alpine) {
    Alpine.data('reportFilters', (preset) => ({
        preset,
        advanced: false,
        mobile: window.matchMedia('(max-width: 767.98px)').matches,
        chips: [],
        media: null,
        handleResize: null,
        init() {
            this.media = window.matchMedia('(max-width: 767.98px)');
            this.handleResize = (event) => { this.mobile = event.matches; };
            this.media.addEventListener('change', this.handleResize);
            const appliedUrl = new URL(window.location.href);
            this.chips = Array.from(this.$el.querySelectorAll('select[name], input[name]:not([type="hidden"])'))
                .filter((field) => appliedUrl.searchParams.has(field.name) && field.value !== '' && !['from', 'to'].includes(field.name) && (field.type !== 'checkbox' || field.checked))
                .map((field) => {
                    const label = field.labels?.[0]?.textContent.trim() || field.name;
                    const value = field.tagName === 'SELECT' ? field.selectedOptions[0]?.textContent.trim() : (field.type === 'checkbox' ? 'نعم' : field.value);
                    const removeUrl = new URL(appliedUrl);
                    removeUrl.searchParams.delete(field.name);
                    removeUrl.searchParams.delete('page');
                    removeUrl.searchParams.delete('export');
                    if (field.name === 'period') {
                        removeUrl.searchParams.delete('from');
                        removeUrl.searchParams.delete('to');
                    }
                    return { name: field.name, label: `${label}: ${value}`, removeUrl: removeUrl.toString() };
                });
        },
        destroy() {
            this.media?.removeEventListener('change', this.handleResize);
        },
    }));
}
