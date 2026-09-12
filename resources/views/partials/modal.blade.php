{{-- Reusable Confirmation Modal Component --}}
<div x-data="{ open: false, title: '', message: '', actionUrl: '', method: 'POST' }"
     @open-confirm-modal.window="open = true; title = $event.detail.title; message = $event.detail.message; actionUrl = $event.detail.actionUrl; method = $event.detail.method || 'POST'"
     @keydown.escape.window="open = false"
     x-show="open" 
     x-cloak
     class="modal-backdrop-custom"
     role="dialog"
     aria-modal="true"
     aria-labelledby="confirm-modal-title"
     style="position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); display: flex; align-items: center; justify-content: center;">
    
    <div @click.outside="open = false" class="modal-dialog-custom card shadow-lg border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-danger text-white py-3 px-4 d-flex justify-content-between align-items-center">
            <h5 id="confirm-modal-title" class="card-title mb-0 fs-6 fw-bold" x-text="title || 'تأكيد الإجراء'"></h5>
            <button type="button" @click="open = false" class="btn-close btn-close-white" aria-label="إغلاق نافذة التأكيد"></button>
        </div>
        <div class="card-body p-4 text-center">
            <div class="mb-3 text-warning">
                <i class="fas fa-exclamation-triangle fa-3x"></i>
            </div>
            <p class="card-text text-secondary mb-4 fs-6" x-text="message || 'هل أنت تأكد من رغبتك في تنفيذ هذا الإجراء؟ لا يمكن التراجع في بعض الحالات.'"></p>
            
            <form :action="actionUrl" method="POST" class="d-flex justify-content-center gap-3">
                @csrf
                <template x-if="method !== 'POST'">
                    <input type="hidden" name="_method" :value="method">
                </template>
                <button type="button" @click="open = false" class="btn btn-light px-4 py-2 rounded-3 border fw-semibold">إلغاء</button>
                <button type="submit" class="btn btn-danger px-4 py-2 rounded-3 fw-bold">تأكيد الإجراء</button>
            </form>
        </div>
    </div>
</div>
