<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerPortalEvent extends Model
{
    protected $fillable = ['customer_portal_request_id', 'user_id', 'event_type', 'revision', 'message', 'snapshot'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function getLabelAttribute(): string
    {
        return ['SAVED' => 'حفظ المسودة', 'SUBMITTED' => 'إرسال الطلب للمصنع', 'OFFERED' => 'إرسال عرض للمراجعة', 'CONFIRMED' => 'تأكيد العميل للعرض', 'CHANGES_REQUESTED' => 'طلب تعديل', 'AMENDMENT_REQUESTED' => 'طلب مراجعة تعديل بعد التأكيد'][$this->event_type] ?? $this->event_type;
    }
}
