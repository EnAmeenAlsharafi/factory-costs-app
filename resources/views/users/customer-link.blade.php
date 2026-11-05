<div class="col-12">
    <label for="customer_id" class="form-label">العميل / المعرض المرتبط بحساب البوابة</label>
    <select name="customer_id" id="customer_id" class="form-select @error('customer_id') is-invalid @enderror">
        <option value="">بدون ربط — للمستخدمين الداخليين</option>
        @foreach($customers as $customer)
            <option value="{{ $customer->id }}" @selected(old('customer_id', isset($user) ? $user->customer_id : null) == $customer->id)>{{ $customer->name }} — {{ $customer->customer_code }}</option>
        @endforeach
    </select>
    <small class="text-muted">عند اختيار دور «عميل جملة / معرض» يجب تحديد العميل وترك القسم التشغيلي فارغًا. حساب واحد لكل عميل، ويشمل طلباته السابقة.</small>
    @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
