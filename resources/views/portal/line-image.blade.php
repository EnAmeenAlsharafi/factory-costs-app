{{-- Square product thumbnail; falls back to an icon when the product has no image. --}}
<span class="order-line-image">
    @if($imageUrl)
        <img src="{{ $imageUrl }}" alt="{{ $imageAlt }}" loading="lazy">
    @else
        <i class="fas fa-couch" aria-hidden="true"></i>
    @endif
</span>
