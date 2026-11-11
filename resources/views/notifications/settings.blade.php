@extends('layouts.app')

@section('title', 'إعدادات الإشعارات - مصنع مفروشات سدير')
@section('page-title', 'إعدادات الإشعارات')

@section('content')
<div class="container-fluid px-4 py-3">

    <div class="page-header-card mb-4">
        <h1 id="page-heading" class="page-header-title fw-bold text-dark mb-1 d-flex align-items-center gap-2">
            <i class="fas fa-sliders text-warning" aria-hidden="true"></i> مصفوفة الإشعارات
        </h1>
        <p class="page-header-subtitle text-muted mb-0 fs-7">
            لكل إشعار جمهور أو أكثر. أطفئ ما لا يحتاجه المصنع؛ الإشعار الذي أُطفئت كل جماهيره لا يُرسل إطلاقاً.
            «القسم المعني» و«الموظف المعني» يتحددان لحظة وقوع الحدث.
        </p>
    </div>

    <form action="{{ route('settings.notifications.update') }}" method="POST">
        @csrf
        @method('PUT')

        @foreach ($groups as $groupName => $rows)
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-light fw-bold text-dark">{{ $groupName }}</div>
                <ul class="list-group list-group-flush">
                    @foreach ($rows as $row)
                        <li class="list-group-item py-3">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <i class="fas {{ $row['type']->icon() }} text-{{ $row['type']->severity() === 'info' ? 'secondary' : $row['type']->severity() }}" aria-hidden="true"></i>
                                <span class="fw-bold text-dark">{{ $row['type']->label() }}</span>
                                @if ($row['type']->isAutomatic())
                                    <span class="badge bg-info-subtle text-info-emphasis border fs-8">آلي — من الفحص المجدول</span>
                                @endif
                            </div>

                            <div class="d-flex flex-column gap-2 ps-md-4">
                                @foreach ($row['audiences'] as $index => $audience)
                                    @php $toggleId = 'toggle-'.$row['type']->value.'-'.$index; @endphp
                                    <div class="d-flex justify-content-between align-items-center gap-3 bg-light rounded-3 px-3 py-2">
                                        <label for="{{ $toggleId }}" class="d-flex flex-wrap align-items-center gap-2 m-0 fs-7 text-secondary">
                                            <span>{{ $audience['label'] }}</span>
                                            @if ($audience['reach'] !== null)
                                                <span class="badge bg-white text-secondary border fs-8">{{ $audience['reach'] }} موظف</span>
                                            @endif
                                        </label>
                                        <div class="form-check form-switch m-0 flex-shrink-0">
                                            <input class="form-check-input" type="checkbox"
                                                   name="enabled[{{ $row['type']->value }}|{{ $audience['key'] }}]"
                                                   id="{{ $toggleId }}" value="1" @checked($audience['enabled'])>
                                            <span class="visually-hidden">تفعيل «{{ $row['type']->label() }}» لجمهور {{ $audience['label'] }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach

        <div class="position-sticky bottom-0 bg-white border-top py-3 px-3 shadow-sm rounded-3 d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary fw-bold">
                <i class="fas fa-save ms-1" aria-hidden="true"></i> حفظ الإعدادات
            </button>
            <a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary">رجوع للإشعارات</a>
        </div>
    </form>
</div>
@endsection
