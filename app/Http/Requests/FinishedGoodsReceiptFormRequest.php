<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinishedGoodsReceiptFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('finished_goods.receive') ?? false;
    }

    public function rules(): array
    {
        return [
            'production_order_id' => ['required', 'exists:production_orders,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'received_quantity' => ['required_without:quantity', 'nullable', 'numeric', 'gt:0'],
            'quantity' => ['required_without:received_quantity', 'nullable', 'numeric', 'gt:0'],
            'receipt_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
