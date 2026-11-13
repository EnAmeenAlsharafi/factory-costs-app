@extends('layouts.app')

@section('title', 'إنشاء قالب تصنيع')

@section('content')
<div class="container-fluid px-4 py-4">

    @include('partials.page-header', [
        'title' => 'إنشاء قالب تصنيع',
        'subtitle' => 'نمط بنود جاهز يُعاد استخدامه: اختره عند إنشاء وصفة جديدة وتُنسخ بنوده تلقائياً.',
        'icon' => 'fas fa-layer-group',
        'breadcrumbs' => [
            ['title' => 'قوالب التصنيع', 'url' => route('recipes.templates.index')],
            ['title' => 'قالب جديد'],
        ],
    ])

    <div class="mt-4">
        @include('recipes.templates.partials.form', ['template' => null, 'initialItems' => []])
    </div>
</div>
@endsection
