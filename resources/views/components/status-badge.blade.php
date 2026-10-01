@props([
    'domain',
    'status',
    'size' => 'md',
])

@php
    $presented = \App\Services\StatusPresenter::present($domain, $status);
@endphp

{{-- Status is always communicated with text + icon, never color alone. --}}
<span {{ $attributes->class(['status-chip', 'status-chip-'.$presented['tone'], 'status-chip-lg' => $size === 'lg']) }} data-status="{{ $status }}">
    <i class="fas {{ $presented['icon'] }}" aria-hidden="true"></i>
    <span>{{ $presented['label'] }}</span>
</span>
