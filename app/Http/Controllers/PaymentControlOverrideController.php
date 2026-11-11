<?php

namespace App\Http\Controllers;

use App\Models\CustomerOrder;
use App\Models\PaymentControlOverride;
use App\Notifications\NotificationService;
use App\Notifications\NotificationType;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentControlOverrideController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_if(! $request->user()->can('receivables.override_payment_control'), 403, 'غير مصرح لك بتسجيل استثناءات قيود السداد والائتمان.');

        $validated = $request->validate([
            'customer_order_id' => 'required|exists:customer_orders,id',
            'override_stage' => 'required|in:PRODUCTION_RELEASE,DELIVERY_DISPATCH,CREDIT_LIMIT',
            'reason' => 'required|string|min:5',
        ]);

        try {
            $order = CustomerOrder::with('customer')->findOrFail($validated['customer_order_id']);

            $override = PaymentControlOverride::create([
                'customer_order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'override_stage' => $validated['override_stage'],
                'requested_amount' => $order->outstanding_balance,
                'credit_limit' => $order->customer && $order->customer->creditProfile ? $order->customer->creditProfile->credit_limit : 0,
                'current_exposure' => $order->customer ? $order->customer->current_credit_exposure : 0,
                'reason' => $validated['reason'],
                'created_by_user_id' => $request->user()->id,
            ]);

            app(NotificationService::class)->raise(NotificationType::PaymentControlOverridden, [
                'title' => 'سُجّل استثناء إداري على قيد السداد',
                'body' => "طلب العميل {$order->order_number} — {$order->customer?->name} — المرحلة: {$override->override_stage} — السبب: {$override->reason}",
                'url' => route('sales.orders.show', $order->id),
                'subject' => $order,
                'actor' => $request->user(),
            ]);

            return back()->with('success', 'تم اعتماد وتسجيل الاستثناء الإداري بنجاح للطلب رقم '.$order->order_number.'.');
        } catch (Exception $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
