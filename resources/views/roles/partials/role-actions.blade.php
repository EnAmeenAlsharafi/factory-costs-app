{{-- Actions for one role row/card: view, edit permissions, and (department roles) duplicate / delete. --}}
<a href="{{ route('roles.show', $role) }}" class="btn btn-sm btn-light border text-secondary" title="عرض الصلاحيات">
    <i class="fas fa-eye me-1" aria-hidden="true"></i> استعراض
</a>
@can('roles.manage')
    @if ($role->name !== 'admin')
        <a href="{{ route('roles.edit', $role) }}" class="btn btn-sm btn-light border text-primary" title="تعديل مصفوفة الصلاحيات">
            <i class="fas fa-sliders-h me-1" aria-hidden="true"></i> تخصيص
        </a>
    @endif
    @if ($role->department_id)
        <form action="{{ route('roles.duplicate', $role) }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-light border text-secondary" title="نسخ الدور بصلاحياته">
                <i class="fas fa-copy me-1" aria-hidden="true"></i> نسخ
            </button>
        </form>
        @if ($role->users_count === 0)
            <button type="button" class="btn btn-sm btn-light border text-danger"
                    @click="$dispatch('open-confirm-modal', {
                        title: @js('حذف الدور «' . $role->display_name . '» نهائياً'),
                        message: @js('سيُحذف الدور وصلاحياته من قسم ' . ($role->department?->name_ar ?? '') . ' ولا يمكن استرجاعه. لا يتأثر أي موظف لأن الدور غير مسند لأحد.'),
                        actionUrl: @js(route('roles.destroy', $role)),
                        method: 'DELETE'
                    })">
                <i class="fas fa-trash me-1" aria-hidden="true"></i> حذف
            </button>
        @endif
    @endif
@endcan
