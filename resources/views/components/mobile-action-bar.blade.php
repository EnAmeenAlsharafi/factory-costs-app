{{--
    Contextual primary-action area.
    < 768px: fixed to the bottom of the viewport (safe-area aware) with a spacer so content is never covered.
    >= 768px: renders inline as a normal right-aligned action row, so the same buttons serve desktop too.
--}}
<div class="mobile-action-bar-spacer" aria-hidden="true"></div>
<div {{ $attributes->class(['mobile-action-bar']) }} role="region" aria-label="الإجراءات الرئيسية">
    {{ $slot }}
</div>
