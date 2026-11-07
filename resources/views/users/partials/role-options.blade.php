{{-- Role options grouped as global roles plus one group per department; department roles only show for the selected department. --}}
@php
    $globalRoles = $roles->whereNull('department_id');
    $departmentRoleGroups = $roles->whereNotNull('department_id')->groupBy('department_id');
@endphp
<optgroup label="أدوار عامة">
    @foreach ($globalRoles as $role)
        <option value="{{ $role->id }}" {{ (string) $selectedRoleId === (string) $role->id ? 'selected' : '' }}>
            {{ $role->display_name }}{{ $role->name === 'production_worker' ? ' (قالب قديم)' : '' }}
        </option>
    @endforeach
</optgroup>
@foreach ($departmentRoleGroups as $departmentId => $departmentRoles)
    <optgroup label="أدوار قسم {{ $departmentRoles->first()->department?->name_ar }}"
              x-show="departmentId === '{{ $departmentId }}'"
              :disabled="departmentId !== '{{ $departmentId }}'">
        @foreach ($departmentRoles as $role)
            <option value="{{ $role->id }}" {{ (string) $selectedRoleId === (string) $role->id ? 'selected' : '' }}>
                {{ $role->display_name }}
            </option>
        @endforeach
    </optgroup>
@endforeach
