/**
 * Arabic Factory Production Costs - Client Interactivity & Calculation Preview
 */

document.addEventListener('DOMContentLoaded', () => {
    initDailyEntryForm();
    initDuplicateDateChecker();
    initDeleteModals();
});

function formatSAR(num) {
    return (Number(num) || 0).toLocaleString('ar-SA', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }) + ' ر.س';
}

function initDailyEntryForm() {
    const form = document.getElementById('dailyReportForm');
    if (!form) return;

    let isDirty = false;
    let isSubmitting = false;

    // Track dirty state
    form.addEventListener('input', () => {
        isDirty = true;
    });

    window.addEventListener('beforeunload', (e) => {
        if (isDirty && !isSubmitting) {
            e.preventDefault();
            e.returnValue = 'لديك تغييرات غير محفوظة، هل أنت متأكد من المغادرة؟';
            return e.returnValue;
        }
    });

    form.addEventListener('submit', (e) => {
        isSubmitting = true;
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = `
                <svg style="width: 1.25rem; height: 1.25rem; animation: spin 1s linear infinite; display: inline-block; vertical-align: middle;" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="12" cy="12" r="10" stroke-width="4" style="opacity: 0.25;"></circle>
                    <path d="M4 12a8 8 0 018-8" stroke-width="4"></path>
                </svg>
                جاري الحفظ...
            `;
        }
    });

    // Handle syncing between desktop table and mobile cards
    const qtyInputs = document.querySelectorAll('.input-qty');
    const storageSelects = document.querySelectorAll('.select-storage');
    const companySelects = document.querySelectorAll('.select-company');
    const typeSelects = document.querySelectorAll('.select-type');

    function syncAndRecalculate(e) {
        const target = e.target;
        const index = target.dataset.index;
        const field = target.dataset.field;

        // Sync desktop <-> mobile inputs of the same index and field
        const sisterInputs = document.querySelectorAll(`[data-index="${index}"][data-field="${field}"]`);
        sisterInputs.forEach(input => {
            if (input !== target) {
                input.value = target.value;
            }
        });

        // Highlight row / card
        const qtyVal = parseInt(target.closest('tr, .prod-card')?.querySelector('.input-qty')?.value || 0, 10);
        const row = document.getElementById(`row-${index}`);
        const card = document.getElementById(`card-${index}`);
        if (qtyVal > 0) {
            row?.classList.add('row-active');
            card?.classList.add('has-qty');
        } else {
            row?.classList.remove('row-active');
            card?.classList.remove('has-qty');
        }

        updateLiveSummary();
    }

    qtyInputs.forEach(input => input.addEventListener('input', syncAndRecalculate));
    storageSelects.forEach(select => select.addEventListener('change', syncAndRecalculate));
    companySelects.forEach(select => select.addEventListener('change', syncAndRecalculate));
    typeSelects.forEach(select => select.addEventListener('change', syncAndRecalculate));

    function updateLiveSummary() {
        // Read products standard data attached to form dataset
        const standardsMetaElem = document.getElementById('standardsMetadata');
        const fabricPricesElem = document.getElementById('fabricPricesMetadata');
        if (!standardsMetaElem || !fabricPricesElem) return;

        const standards = JSON.parse(standardsMetaElem.textContent || '{}');
        const fabricPrices = JSON.parse(fabricPricesElem.textContent || '{}');

        let totalQty = 0;
        let activeCount = 0;
        let totalCost = 0.0;

        // Iterate through unique product rows
        const rows = document.querySelectorAll('.desktop-table-view tbody tr');
        rows.forEach((tr) => {
            const index = tr.dataset.index;
            const prodId = tr.dataset.productId;
            const code = tr.dataset.code;

            const qtyInput = tr.querySelector('.input-qty');
            const storageSelect = tr.querySelector('.select-storage');
            const companySelect = tr.querySelector('.select-company');
            const typeSelect = tr.querySelector('.select-type');

            const qty = parseInt(qtyInput?.value || 0, 10);
            if (qty > 0) {
                totalQty += qty;
                activeCount++;

                const storage = storageSelect?.value || 'بدون تخزين';
                const companyId = companySelect?.value;
                const typeId = typeSelect?.value;

                const std = standards[code] || {};
                const wood = (storage === 'بتخزين') ? Number(std.wood_with_storage || 0) : Number(std.wood_without_storage || 0);
                const foam = Number(std.foam || 0);
                const meters = Number(std.fabric_meters || 0);
                const paint = Number(std.paint || 0);
                const nails = Number(std.nails || 0);
                const pack = Number(std.packaging || 0);
                const hinges = Number(std.hinges || 0);
                const transport = Number(std.raw_material_transport || 0);
                const carp = Number(std.carpentry_wages || 0);
                const uph = Number(std.upholstery_wages || 0);
                const packW = Number(std.packaging_wages || 0);
                const adm = Number(std.administrative_wages || 0);
                const adv = Number(std.advertising || 0);
                const ship = Number(std.shipping || 0);
                const misc = Number(std.miscellaneous || 0);
                const profit = Number(std.profit_margin || 0);

                let fabricPricePerMeter = 0.0;
                if (companyId && typeId) {
                    const pKey = `${companyId}_${typeId}`;
                    fabricPricePerMeter = Number(fabricPrices[pKey] || 0);
                }
                const fabricCost = meters * fabricPricePerMeter;

                const unitTotal = wood + foam + fabricCost + paint + nails + pack + hinges +
                                  transport + carp + uph + packW + adm + adv + ship + misc + profit;
                
                const lineTotal = unitTotal * qty;
                totalCost += lineTotal;

                // Update line preview in desktop row
                const lineCell = tr.querySelector('.cell-line-total');
                if (lineCell) {
                    lineCell.textContent = formatSAR(lineTotal);
                }
            } else {
                const lineCell = tr.querySelector('.cell-line-total');
                if (lineCell) {
                    lineCell.textContent = '0.00 ر.س';
                }
            }
        });

        // Update fixed bottom bar
        const totalQtyElem = document.getElementById('summaryTotalQty');
        const activeCountElem = document.getElementById('summaryActiveCount');
        const totalCostElem = document.getElementById('summaryTotalCost');

        if (totalQtyElem) totalQtyElem.textContent = totalQty.toLocaleString('ar-SA');
        if (activeCountElem) activeCountElem.textContent = activeCount.toLocaleString('ar-SA');
        if (totalCostElem) totalCostElem.textContent = formatSAR(totalCost);
    }

    // Run initial calculation
    updateLiveSummary();
}

function initDuplicateDateChecker() {
    const dateInput = document.getElementById('production_date');
    const alertBox = document.getElementById('dateDuplicateAlert');
    const alertMsg = document.getElementById('dateDuplicateMessage');
    const alertLink = document.getElementById('dateDuplicateLink');

    if (!dateInput || !alertBox) return;

    dateInput.addEventListener('change', async () => {
        const val = dateInput.value;
        if (!val) {
            alertBox.style.display = 'none';
            return;
        }

        try {
            const resp = await fetch(`/reports/check-date?date=${encodeURIComponent(val)}`);
            if (resp.ok) {
                const data = await resp.json();
                if (data.exists) {
                    alertBox.style.display = 'flex';
                    if (alertMsg) alertMsg.textContent = `تنبيه: يوجد سجل إنتاج محفوظ لتاريخ ${val} مسبقاً.`;
                    if (alertLink) {
                        alertLink.href = data.edit_url;
                        alertLink.style.display = 'inline-flex';
                    }
                } else {
                    alertBox.style.display = 'none';
                }
            }
        } catch (e) {
            console.error('Date check error', e);
        }
    });
}

function initDeleteModals() {
    const deleteBtns = document.querySelectorAll('.btn-confirm-delete');
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            const confirmed = confirm('هل أنت متأكد من رغبتك في حذف هذا التقرير اليومي؟ يمكن استعادته لاحقاً.');
            if (!confirmed) {
                e.preventDefault();
            }
        });
    });
}
