@extends('layouts.app')

@section('title', 'تعديل قالب التصنيع - ' . $template->template_code)

@section('content')
<div class="container-fluid px-4 py-4">

    @include('partials.page-header', [
        'title' => 'تعديل القالب: ' . $template->name_ar,
        'subtitle' => 'التعديل يؤثر على الوصفات التي ستُنشأ لاحقاً فقط، ولا يغيّر الوصفات المنشأة سابقاً من هذا القالب.',
        'icon' => 'fas fa-pen-to-square',
        'breadcrumbs' => [
            ['title' => 'قوالب التصنيع', 'url' => route('recipes.templates.index')],
            ['title' => $template->template_code, 'url' => route('recipes.templates.show', $template)],
            ['title' => 'تعديل'],
        ],
    ])

    <div class="mt-4">
        @include('recipes.templates.partials.form', ['template' => $template, 'initialItems' => $initialItems])
    </div>
</div>
@endsection
