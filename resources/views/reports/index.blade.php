@extends('layouts.app')

@section('title', 'السجلات اليومية — إدارة تكاليف المصنع')

@section('content')
<div class="page-header" style="margin-bottom: 1.5rem;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h1 style="font-size: 1.5rem; margin-bottom: 0.25rem;">السجلات اليومية لتكاليف الإنتاج</h1>
            <p style="color: var(--text-muted); font-size: 0.9rem;">استعراض ومراجعة وتعديل تقارير وتكاليف أيام الإنتاج السابقة</p>
        </div>
        <div>
            <a href="{{ route('reports.create') }}" class="btn btn-primary">
                + إدخال إنتاج يوم جديد
            </a>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-body" style="padding: 1.25rem;">
        <form action="{{ route('reports.index') }}" method="GET">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: flex-end;">
                <div>
                    <label for="date" class="form-label" style="font-size: 0.85rem;">تاريخ محدد</label>
                    <input type="date" id="date" name="date" class="form-control" value="{{ request('date') }}">
                </div>
                <div>
                    <label for="from_date" class="form-label" style="font-size: 0.85rem;">من تاريخ</label>
                    <input type="date" id="from_date" name="from_date" class="form-control" value="{{ request('from_date') }}">
                </div>
                <div>
                    <label for="to_date" class="form-label" style="font-size: 0.85rem;">إلى تاريخ</label>
                    <input type="date" id="to_date" name="to_date" class="form-control" value="{{ request('to_date') }}">
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        تصفية
                    </button>
                    @if(request()->hasAny(['date', 'from_date', 'to_date']))
                        <a href="{{ route('reports.index') }}" class="btn btn-secondary" title="إلغاء التصفية">
                            إلغاء
                        </a>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Reports Table Card -->
<div class="card">
    @if($reports->isEmpty())
        <div style="padding: 4rem 1.5rem; text-align: center; color: var(--text-muted);">
            <div style="font-size: 3rem; margin-bottom: 0.5rem;">📋</div>
            <h3 style="font-size: 1.2rem; color: var(--text-heading); margin-bottom: 0.5rem;">لا توجد سجلات إنتاج</h3>
            <p style="margin-bottom: 1.5rem; font-size: 0.95rem;">لم يتم العثور على أي تقارير إنتاج تطابق خيارات البحث المحددة.</p>
            <a href="{{ route('reports.create') }}" class="btn btn-primary">
                بدء إدخال إنتاج اليوم
            </a>
        </div>
    @else
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 140px;">تاريخ الإنتاج</th>
                        <th style="width: 120px; text-align: center;">إجمالي القطع</th>
                        <th style="width: 160px; text-align: left;">إجمالي التكلفة اليومية</th>
                        <th style="width: 160px;">آخر تحديث</th>
                        <th style="width: 150px; text-align: center;">إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reports as $report)
                        <tr>
                            <td style="font-weight: 700; color: var(--text-heading);">
                                <a href="{{ route('reports.show', $report) }}" style="color: inherit; text-decoration: none;">
                                    {{ $report->production_date->format('Y-m-d') }}
                                </a>
                            </td>
                            <td style="text-align: center; font-weight: 700;">
                                <span class="badge badge-primary" style="font-size: 0.9rem; padding: 0.3rem 0.75rem;">
                                    {{ number_format($report->total_quantity) }} قطعة
                                </span>
                            </td>
                            <td style="text-align: left; font-weight: 800; color: var(--primary); font-size: 1rem;">
                                {{ number_format($report->total_cost, 2) }} ر.س
                            </td>
                            <td style="color: var(--text-muted); font-size: 0.85rem;">
                                {{ $report->updated_at->format('Y-m-d H:i') }}
                            </td>
                            <td style="text-align: center;">
                                <div style="display: inline-flex; gap: 0.35rem;">
                                    <a href="{{ route('reports.show', $report) }}" class="btn btn-sm btn-secondary" title="عرض التفاصيل">
                                        عرض
                                    </a>
                                    <a href="{{ route('reports.edit', $report) }}" class="btn btn-sm btn-secondary" title="تعديل">
                                        تعديل
                                    </a>
                                    <form action="{{ route('reports.destroy', $report) }}" method="POST" style="display: inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger btn-confirm-delete" title="حذف السجل">
                                            حذف
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($reports->hasPages())
            <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border-color); display: flex; justify-content: center;">
                {{ $reports->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
