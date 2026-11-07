<?php

namespace App\Http\Requests\Concerns;

use App\Models\ProductionOrder;
use App\Models\ProductionOrderOperation;
use Illuminate\Validation\Validator;

/**
 * Keeps department-restricted users' production reports (quality, waste) inside their own department.
 */
trait ValidatesProductionDepartmentScope
{
    protected function validateProductionDepartmentScope(Validator $validator): void
    {
        $user = $this->user();

        if (! $user?->isDepartmentRestricted()) {
            return;
        }

        if ($this->filled('production_order_id')
            && ! ProductionOrder::visibleTo($user)->whereKey($this->input('production_order_id'))->exists()) {
            $validator->errors()->add('production_order_id', 'أمر الإنتاج المختار لا يمر بقسمك التشغيلي.');
        }

        if ($this->filled('production_order_operation_id')
            && ! ProductionOrderOperation::visibleTo($user)->whereKey($this->input('production_order_operation_id'))->exists()) {
            $validator->errors()->add('production_order_operation_id', 'لا يمكن التسجيل على عملية خارج قسمك التشغيلي.');
        }

        if ($this->filled('detected_department_id') && (int) $this->input('detected_department_id') !== $user->productionDepartmentId()) {
            $validator->errors()->add('detected_department_id', 'قسم الاكتشاف يجب أن يكون قسمك التشغيلي.');
        }
    }
}
