{{-- Product cards nested in a request/order card. A long order stays scannable: its details page lists everything. --}}
@php($visibleLineCount = 3)
@if($lines !== [])
    <ul class="order-line-cards" aria-label="منتجات الطلب">
        @foreach(array_slice($lines, 0, $visibleLineCount) as $line)
            <li class="order-line-card">
                @include('portal.line-image', ['imageUrl' => $line['image_url'], 'imageAlt' => $line['name']])
                <div class="min-w-0">
                    <div class="order-line-card-name">{{ $line['name'] }}</div>
                    <div class="order-line-card-meta">
                        <span>{{ $line['description'] }}</span>
                        @if($line['size'])
                            <span><span class="ltr-isolate">{{ $line['size'] }}</span> سم</span>
                        @endif
                        <span>الكمية {{ number_format($line['quantity'], 0) }}</span>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
    @if(count($lines) > $visibleLineCount)
        <a class="fs-7 fw-semibold text-decoration-none" href="{{ $detailsUrl }}">عرض كل المنتجات ({{ count($lines) }})</a>
    @endif
@endif
