<?php

namespace App\Services;

/**
 * Single source of truth for how workflow statuses are presented in the UI
 * (Arabic label + color tone + icon), shared by desktop and mobile markup so
 * status language never diverges between screens.
 */
class StatusPresenter
{
    /**
     * @var array<string, array<string, array{label: string, tone: string, icon: string}>>
     */
    private const MAP = [
        'delivery' => [
            'DRAFT' => ['label' => 'مسودة', 'tone' => 'secondary', 'icon' => 'fa-file-pen'],
            'READY_FOR_DELIVERY' => ['label' => 'جاهز للتوصيل', 'tone' => 'primary', 'icon' => 'fa-box'],
            'ASSIGNED' => ['label' => 'مُسند للسائق', 'tone' => 'info', 'icon' => 'fa-user-check'],
            'OUT_FOR_DELIVERY' => ['label' => 'خارج للتوصيل', 'tone' => 'warning', 'icon' => 'fa-truck-fast'],
            'DELIVERED' => ['label' => 'تم التسليم', 'tone' => 'success', 'icon' => 'fa-circle-check'],
            'INSTALLATION_COMPLETED' => ['label' => 'تم التركيب', 'tone' => 'success', 'icon' => 'fa-screwdriver-wrench'],
            'FAILED' => ['label' => 'تعذر التسليم', 'tone' => 'danger', 'icon' => 'fa-triangle-exclamation'],
            'RESCHEDULED' => ['label' => 'أعيدت جدولته', 'tone' => 'dark', 'icon' => 'fa-calendar-days'],
            'CANCELLED' => ['label' => 'ملغى', 'tone' => 'secondary', 'icon' => 'fa-ban'],
        ],
        'operation' => [
            'PENDING' => ['label' => 'في الانتظار', 'tone' => 'secondary', 'icon' => 'fa-hourglass-start'],
            'READY' => ['label' => 'جاهز للبدء', 'tone' => 'info', 'icon' => 'fa-play'],
            'IN_PROGRESS' => ['label' => 'قيد التنفيذ', 'tone' => 'warning', 'icon' => 'fa-gears'],
            'PARTIALLY_COMPLETED' => ['label' => 'منجز جزئياً', 'tone' => 'primary', 'icon' => 'fa-circle-half-stroke'],
            'COMPLETED' => ['label' => 'مكتمل', 'tone' => 'success', 'icon' => 'fa-circle-check'],
            'BLOCKED' => ['label' => 'متوقف', 'tone' => 'danger', 'icon' => 'fa-hand'],
            'SKIPPED' => ['label' => 'متخطى', 'tone' => 'dark', 'icon' => 'fa-forward'],
        ],
        'material_request' => [
            'DRAFT' => ['label' => 'مسودة', 'tone' => 'secondary', 'icon' => 'fa-file-pen'],
            'SUBMITTED' => ['label' => 'بانتظار الصرف', 'tone' => 'warning', 'icon' => 'fa-hourglass-half'],
            'PARTIALLY_FULFILLED' => ['label' => 'مصروف جزئياً', 'tone' => 'primary', 'icon' => 'fa-circle-half-stroke'],
            'FULFILLED' => ['label' => 'مصروف بالكامل', 'tone' => 'success', 'icon' => 'fa-circle-check'],
            'CANCELLED' => ['label' => 'ملغى', 'tone' => 'secondary', 'icon' => 'fa-ban'],
        ],
        'document' => [
            'DRAFT' => ['label' => 'مسودة', 'tone' => 'secondary', 'icon' => 'fa-file-pen'],
            'POSTED' => ['label' => 'مُرحّل', 'tone' => 'success', 'icon' => 'fa-lock'],
            'CANCELLED' => ['label' => 'ملغى', 'tone' => 'secondary', 'icon' => 'fa-ban'],
        ],
    ];

    /**
     * @return array{label: string, tone: string, icon: string}
     */
    public static function present(string $domain, ?string $status): array
    {
        $status = (string) $status;

        return self::MAP[$domain][$status] ?? [
            'label' => $status !== '' ? $status : 'غير محدد',
            'tone' => 'secondary',
            'icon' => 'fa-circle-info',
        ];
    }

    public static function label(string $domain, ?string $status): string
    {
        return self::present($domain, $status)['label'];
    }
}
