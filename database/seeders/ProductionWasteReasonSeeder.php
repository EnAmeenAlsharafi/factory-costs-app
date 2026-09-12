<?php

namespace Database\Seeders;

use App\Models\ProductionWasteReason;
use Illuminate\Database\Seeder;

class ProductionWasteReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            [
                'code' => 'WRONG_SIZE',
                'name_ar' => 'خطأ في المقاسات / الأبعاد',
                'description' => 'قص أو تشكيل أبعاد غير مطابقة للمواصفات الفنية',
            ],
            [
                'code' => 'WRONG_FABRIC',
                'name_ar' => 'خطأ في نوع القماش',
                'description' => 'استخدام نوع قماش غير المطلوب في أمر الإنتاج',
            ],
            [
                'code' => 'WRONG_COLOR',
                'name_ar' => 'خطأ في اللون',
                'description' => 'استخدام لون غير المطلوب أو عدم تطابق درجات اللون',
            ],
            [
                'code' => 'CUTTING_ERROR',
                'name_ar' => 'خطأ في القص والتقطيع',
                'description' => 'خطأ أثناء عمليات التقطيع أو الخراطة أو النشر',
            ],
            [
                'code' => 'DAMAGE_DURING_PRODUCTION',
                'name_ar' => 'تلف أثناء الإنتاج',
                'description' => 'تلف خامات أو أجزاء أثناء مراحل التجميع أو التنجيد أو الدهان',
            ],
            [
                'code' => 'DAMAGE_DURING_LOADING',
                'name_ar' => 'تلف أثناء المناولة / التحميل',
                'description' => 'تلف ناتج عن النقل الداخلي أو المناولة بين الأقسام',
            ],
            [
                'code' => 'DEFECTIVE_MATERIAL',
                'name_ar' => 'خامة تالفة / عيب مورد',
                'description' => 'اكتشاف عيب خفي في الخامة الموردة أثناء التشغيل',
            ],
            [
                'code' => 'REMAKE',
                'name_ar' => 'إعادة تصنيع قطاع تالف',
                'description' => 'هدر ناتج عن قرار إعادة تصنيع قطعة تالفة بالكامل',
            ],
            [
                'code' => 'OTHER',
                'name_ar' => 'أسباب أخرى',
                'description' => 'أي أسباب هدر إضافية غير مدرجة أعلاه',
            ],
        ];

        foreach ($reasons as $reason) {
            ProductionWasteReason::updateOrCreate(
                ['code' => $reason['code']],
                $reason
            );
        }
    }
}
