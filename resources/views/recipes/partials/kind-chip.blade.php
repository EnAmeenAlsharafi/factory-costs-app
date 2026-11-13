{{-- What a BOM line or recipe target is, in words + icon (never colour alone). --}}
@php
    $kind = match ($type) {
        'MATERIAL' => ['material', 'fa-cube', 'مادة خام'],
        'SEMI_FINISHED_COMPONENT' => ['component', 'fa-cubes', 'مكون نصف مصنع'],
        default => ['product', 'fa-couch', 'منتج نهائي'],
    };
@endphp
<span class="kind-chip kind-chip-{{ $kind[0] }}"><i class="fas {{ $kind[1] }}" aria-hidden="true"></i> {{ $kind[2] }}</span>
