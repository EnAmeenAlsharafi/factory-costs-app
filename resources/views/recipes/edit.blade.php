@extends('layouts.app')

@section('title', 'تعديل المسودة V' . $version->version_number . ' - ' . $recipe->recipe_code)

@section('content')
<div class="container-fluid px-4 py-4">

    @include('partials.page-header', [
        'title' => 'تعديل بنود المسودة V' . $version->version_number,
        'subtitle' => $recipe->name . ' — التعديل على المسودة فقط، والإصدار المعتمد يبقى كما هو حتى تعتمد هذه المسودة.',
        'icon' => 'fas fa-pen-to-square',
        'breadcrumbs' => [
            ['title' => 'وصفات التصنيع', 'url' => route('recipes.index')],
            ['title' => $recipe->recipe_code, 'url' => route('recipes.show', [$recipe, 'version_id' => $version->id])],
            ['title' => 'تعديل V' . $version->version_number],
        ],
    ])

    <form action="{{ route('recipes.versions.update', [$recipe, $version]) }}" method="POST" class="mt-4" novalidate>
        @csrf
        @method('PUT')

        <section class="card border-0 shadow-sm rounded-3 mb-4" aria-label="ملاحظات الإصدار">
            <div class="card-body">
                <label for="version-notes" class="form-label fw-bold fs-7">ملاحظات V{{ $version->version_number }} <span class="text-muted fw-normal">(اختياري)</span></label>
                <input type="text" id="version-notes" name="notes" class="form-control @error('notes') is-invalid @enderror" value="{{ old('notes', $version->notes) }}" placeholder="مثل: تغيير سماكة الخشب إلى 18 مم">
                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </section>

        <x-bom-editor :materials="$materials" :components="$components" :units="$units" :items="old('items', $initialItems)"
                      title="بنود V{{ $version->version_number }}" hint="اختيار المادة يملأ وحدتها الأساسية تلقائياً.">
            <x-slot:actions>
                <x-mobile-action-bar>
                    <a href="{{ route('recipes.show', [$recipe, 'version_id' => $version->id]) }}" class="btn btn-outline-secondary px-4">إلغاء</a>
                    <button type="submit" class="btn btn-warning px-4 fw-bold" :disabled="items.length === 0">
                        <i class="fas fa-save" aria-hidden="true"></i> حفظ المسودة
                    </button>
                </x-mobile-action-bar>
            </x-slot:actions>
        </x-bom-editor>
    </form>
</div>
@endsection
