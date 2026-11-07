{{-- Staff count and permission count for one role. --}}
<span class="badge bg-light text-dark border px-2 py-1 text-nowrap">
    <i class="fas fa-users me-1 text-muted" aria-hidden="true"></i> {{ $role->users_count }} مستخدم
</span>
@if ($role->name === 'admin')
    <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle px-2 py-1 text-nowrap">
        <i class="fas fa-check-double me-1" aria-hidden="true"></i> كافة الصلاحيات (شامل)
    </span>
@else
    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1 text-nowrap">
        <i class="fas fa-key me-1" aria-hidden="true"></i> {{ $role->permissions_count }} صلاحية
    </span>
@endif
