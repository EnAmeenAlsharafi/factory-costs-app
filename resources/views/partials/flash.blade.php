{{-- Arabic RTL Flash Alerts with Alpine Auto-dismiss --}}
@if (session('success'))
    <div x-data="{ show: true }" 
         x-init="setTimeout(() => show = false, 6000)" 
         x-show="show" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform -translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform translate-y-0"
         x-transition:leave-end="opacity-0 transform -translate-y-2"
         class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm border-0 border-start border-4 border-success bg-white" 
         role="alert">
        <div class="rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center p-2 me-3 flex-shrink-0" style="width: 38px; height: 38px;">
            <i class="fas fa-check-circle fs-5"></i>
        </div>
        <div class="flex-grow-1">
            <h6 class="fw-bold text-success mb-0 fs-7">تمت العملية بنجاح</h6>
            <span class="text-dark fs-7">{{ session('success') }}</span>
        </div>
        <button type="button" @click="show = false" class="btn-close" aria-label="إغلاق الرسالة"></button>
    </div>
@endif

@if (session('error'))
    <div x-data="{ show: true }" 
         x-show="show" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform -translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform translate-y-0"
         x-transition:leave-end="opacity-0 transform -translate-y-2"
         class="alert alert-danger alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm border-0 border-start border-4 border-danger bg-white" 
         role="alert">
        <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center p-2 me-3 flex-shrink-0" style="width: 38px; height: 38px;">
            <i class="fas fa-exclamation-triangle fs-5"></i>
        </div>
        <div class="flex-grow-1">
            <h6 class="fw-bold text-danger mb-0 fs-7">تنبيه بالنظام</h6>
            <span class="text-dark fs-7">{{ session('error') }}</span>
        </div>
        <button type="button" @click="show = false" class="btn-close" aria-label="إغلاق الرسالة"></button>
    </div>
@endif

@if (session('info'))
    <div x-data="{ show: true }" 
         x-init="setTimeout(() => show = false, 7000)" 
         x-show="show" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform -translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform translate-y-0"
         x-transition:leave-end="opacity-0 transform -translate-y-2"
         class="alert alert-info alert-dismissible fade show d-flex align-items-center mb-4 shadow-sm border-0 border-start border-4 border-info bg-white" 
         role="alert">
        <div class="rounded-circle bg-info bg-opacity-10 text-info d-flex align-items-center justify-content-center p-2 me-3 flex-shrink-0" style="width: 38px; height: 38px;">
            <i class="fas fa-info-circle fs-5"></i>
        </div>
        <div class="flex-grow-1">
            <h6 class="fw-bold text-info mb-0 fs-7">إشعار</h6>
            <span class="text-dark fs-7">{{ session('info') }}</span>
        </div>
        <button type="button" @click="show = false" class="btn-close" aria-label="إغلاق الرسالة"></button>
    </div>
@endif

@if ($errors->any())
    <div x-data="{ show: true }" 
         x-show="show" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform -translate-y-2"
         x-transition:enter-end="opacity-100 transform translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 transform translate-y-0"
         x-transition:leave-end="opacity-0 transform -translate-y-2"
         class="alert alert-danger alert-dismissible fade show mb-4 shadow-sm border-0 border-start border-4 border-danger bg-white" 
         role="alert">
        <div class="d-flex align-items-center mb-2">
            <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center p-2 me-2 flex-shrink-0" style="width: 32px; height: 32px;">
                <i class="fas fa-times-circle fs-6"></i>
            </div>
            <h6 class="fw-bold text-danger mb-0 fs-7">يرجى تصحيح أخطاء الإدخال التالية:</h6>
        </div>
        <ul class="mb-0 pe-4 text-dark fs-7">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" @click="show = false" class="btn-close" aria-label="إغلاق الرسالة"></button>
    </div>
@endif
