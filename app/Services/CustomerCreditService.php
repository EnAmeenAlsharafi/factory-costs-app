<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerCreditProfile;
use App\Models\CustomerOrder;
use App\Models\PaymentControlOverride;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CustomerCreditService
{
    public function getCustomerCreditProfile(Customer $customer): CustomerCreditProfile
    {
        return $customer->creditProfile ?: CustomerCreditProfile::create([
            'customer_id' => $customer->id,
            'credit_enabled' => (bool) $customer->is_credit_customer,
            'credit_limit' => (float) ($customer->credit_limit ?? 0.00),
            'credit_days' => 30,
            'warning_threshold_percent' => 80.00,
            'hold_when_exceeded' => true,
        ]);
    }

    public function updateCreditProfile(Customer $customer, array $data, User $user): CustomerCreditProfile
    {
        return DB::transaction(function () use ($customer, $data, $user) {
            $profile = $this->getCustomerCreditProfile($customer);

            $profile->update([
                'credit_enabled' => $data['credit_enabled'] ?? $profile->credit_enabled,
                'credit_limit' => $data['credit_limit'] ?? $profile->credit_limit,
                'credit_days' => $data['credit_days'] ?? $profile->credit_days,
                'warning_threshold_percent' => $data['warning_threshold_percent'] ?? $profile->warning_threshold_percent,
                'hold_when_exceeded' => $data['hold_when_exceeded'] ?? $profile->hold_when_exceeded,
                'approved_by_user_id' => $user->id,
                'approved_at' => now(),
                'notes' => $data['notes'] ?? $profile->notes,
            ]);

            // Sync with Customer table legacy fields if present
            $customer->update([
                'is_credit_customer' => $profile->credit_enabled,
                'credit_limit' => $profile->credit_limit,
            ]);

            return $profile;
        });
    }

    public function calculateExposure(Customer $customer): array
    {
        $profile = $this->getCustomerCreditProfile($customer);
        $limit = (float) $profile->credit_limit;
        $enabled = (bool) $profile->credit_enabled;
        $currentExposure = (float) $customer->current_credit_exposure;
        $availableCredit = max(0.00, $limit - $currentExposure);
        $percentUsed = $limit > 0 ? round(($currentExposure / $limit) * 100, 2) : 0.00;

        $status = 'OK';
        if (! $enabled) {
            $status = 'DISABLED';
        } elseif ($currentExposure > $limit && $limit > 0) {
            $status = 'EXCEEDED';
        } elseif ($percentUsed >= (float) $profile->warning_threshold_percent && $limit > 0) {
            $status = 'WARNING';
        }

        return [
            'credit_enabled' => $enabled,
            'credit_limit' => $limit,
            'credit_days' => $profile->credit_days,
            'current_exposure' => $currentExposure,
            'available_credit' => $availableCredit,
            'percentage_used' => $percentUsed,
            'hold_when_exceeded' => $profile->hold_when_exceeded,
            'status' => $status,
        ];
    }

    public function checkOrderCreditEligibility(CustomerOrder $order): array
    {
        $customer = $order->customer;
        $profile = $this->getCustomerCreditProfile($customer);
        $exposureData = $this->calculateExposure($customer);

        $limit = $exposureData['credit_limit'];
        $enabled = $exposureData['credit_enabled'];
        $currentExposure = $exposureData['current_exposure'];
        $holdWhenExceeded = $exposureData['hold_when_exceeded'];

        // Check for active override
        $override = PaymentControlOverride::where('customer_order_id', $order->id)
            ->where('override_stage', 'CREDIT_LIMIT')
            ->latest()
            ->first();

        if ($override) {
            return [
                'eligible' => true,
                'status' => 'OVERRIDDEN',
                'reason' => 'تم استثناء تجاوز حد الائتمان بموجب موافقة إدارية: '.$override->reason,
                'override' => $override,
                'exposure' => $exposureData,
            ];
        }

        if (! $enabled) {
            return [
                'eligible' => false,
                'status' => 'CREDIT_DISABLED',
                'reason' => 'الائتمان غير مفعّل لحساب هذا العميل.',
                'exposure' => $exposureData,
            ];
        }

        if ($currentExposure > $limit && $holdWhenExceeded) {
            return [
                'eligible' => false,
                'status' => 'EXCEEDED',
                'reason' => sprintf('تم تجاوز حد الائتمان للعميل. التعرض الحالي (%.2f ر.س) يتجاوز الحد المسموح (%.2f ر.س).', $currentExposure, $limit),
                'exposure' => $exposureData,
            ];
        }

        return [
            'eligible' => true,
            'status' => 'OK',
            'reason' => 'ضمن حد الائتمان المسموح.',
            'exposure' => $exposureData,
        ];
    }
}
