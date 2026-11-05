<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerOrder;
use App\Models\CustomerPortalRequest;
use App\Models\CustomerProductAlias;
use App\Models\FabricColor;
use App\Models\ManufacturingRecipe;
use App\Models\Material;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CustomerPortalService
{
    public function __construct(protected CustomerOrderService $orders) {}

    /** @return array<string, mixed> */
    public function catalog(Customer $customer): array
    {
        $aliases = CustomerProductAlias::where('customer_id', $customer->id)->where('is_active', true)->orderByDesc('is_default')->get()->unique('product_model_id')->keyBy('product_model_id');
        $models = ProductModel::where('is_active', true)->orderBy('name_ar')->get(['id', 'name_ar'])->map(fn ($model): array => ['id' => $model->id, 'name' => $aliases->get($model->id)?->customer_product_name ?: $model->name_ar]);
        $readyProducts = Material::active()->sellableReady()->where('has_ready_options', false)->orderBy('name_ar')->get(['id', 'name_ar', 'sales_display_name'])->map(fn ($material): array => ['id' => $material->id, 'name' => $material->sales_display_name ?: $material->name_ar]);
        $fabrics = Material::active()->whereHas('category', fn ($query) => $query->where('code', 'FABRIC'))->orderBy('name_ar')->get(['id', 'name_ar']);
        $colors = FabricColor::active()->available()->whereIn('material_id', $fabrics->pluck('id'))->get(['id', 'material_id', 'color_name_ar', 'color_code']);
        $configurations = ProductConfiguration::where('is_active', true)->get()->map(fn ($configuration): array => ['id' => $configuration->id, 'product_model_id' => $configuration->product_model_id, 'has_storage' => $configuration->has_storage, 'width_cm' => $configuration->width_cm, 'length_cm' => $configuration->length_cm, 'name' => $configuration->display_name]);

        return compact('models', 'readyProducts', 'fabrics', 'colors', 'configurations');
    }

    /** @param array<string, mixed> $data */
    public function saveDraft(User $user, array $data, ?CustomerPortalRequest $submission = null): CustomerPortalRequest
    {
        return $this->saveWithImages(function (array &$storedImages) use ($user, $data, $submission) {
            if ($submission) {
                $submission = $this->locked($submission);
                $this->assertOwner($submission, $user);
                $this->assertRevision($submission, (int) $data['revision']);
                $this->assertStatus($submission, ['DRAFT', 'CHANGES_REQUESTED']);
                $this->assertAmendable($submission);
            } else {
                abort_unless($user->hasRole('customer_portal') && $user->customer?->is_active, 403);
                $submission = new CustomerPortalRequest(['customer_id' => $user->customer_id, 'created_by_user_id' => $user->id, 'revision' => 0]);
            }

            $submission->fill(Arr::only($data, ['customer_reference', 'requested_delivery_date', 'delivery_city', 'delivery_address', 'customer_notes']));
            $submission->fill(['lines' => $this->prepareLines($data['lines'], false, $submission->lines ?? [], $storedImages), 'status' => 'DRAFT', 'revision' => $submission->revision + 1, 'offer_total' => null, 'offered_at' => null]);
            $submission->save();
            $this->record($submission, $user, 'SAVED');

            return $submission;
        });
    }

    public function submit(CustomerPortalRequest $submission, User $user, int $revision): CustomerPortalRequest
    {
        return DB::transaction(function () use ($submission, $user, $revision) {
            $submission = $this->locked($submission);
            $this->assertOwner($submission, $user);
            $this->assertRevision($submission, $revision);
            $this->assertStatus($submission, ['DRAFT']);
            $submission->update(['status' => 'SUBMITTED', 'submitted_at' => now()]);
            $this->record($submission, $user, 'SUBMITTED');

            return $submission;
        });
    }

    /** @param array<string, mixed> $data */
    public function offer(CustomerPortalRequest $submission, User $user, array $data): CustomerPortalRequest
    {
        abort_unless(! $user->isCustomerAccount() && $user->can('orders.update'), 403);

        return $this->saveWithImages(function (array &$storedImages) use ($submission, $user, $data) {
            $submission = $this->locked($submission);
            $this->assertRevision($submission, (int) $data['revision']);
            $this->assertStatus($submission, ['SUBMITTED', 'CHANGES_REQUESTED', 'OFFERED', 'CONFIRMED']);
            $this->assertAmendable($submission);
            if (! $submission->customer->defaultSalesChannel?->is_active) {
                throw ValidationException::withMessages(['offer' => 'حدد قناة بيع افتراضية نشطة في بيانات العميل قبل إرسال العرض.']);
            }
            $lines = $this->prepareLines($data['lines'], true, $submission->lines, $storedImages);
            $cents = 0;
            foreach ($lines as $line) {
                $gross = (int) round((float) $line['unit_price'] * 100) * (int) $line['quantity'];
                $discount = (int) round((float) ($line['discount_amount'] ?? 0) * 100);
                if ($discount > $gross) {
                    throw ValidationException::withMessages(['offer' => 'خصم البند لا يمكن أن يتجاوز قيمته.']);
                }
                $cents += $gross - $discount;
            }
            if ($cents > 999999999999 || (float) ($data['deposit_required_amount'] ?? 0) * 100 > $cents) {
                throw ValidationException::withMessages(['offer' => 'إجمالي العرض غير صالح أو العربون أكبر من قيمة العرض.']);
            }
            $submission->fill(Arr::only($data, ['customer_reference', 'requested_delivery_date', 'delivery_city', 'delivery_address', 'customer_notes', 'promised_delivery_date', 'payment_terms_type', 'deposit_required_amount', 'payment_due_date', 'public_response']));
            if ($data['payment_terms_type'] !== 'DEPOSIT_AND_BALANCE') {
                $submission->deposit_required_amount = null;
            }
            $submission->fill(['lines' => $lines, 'offer_total' => $cents / 100, 'revision' => $submission->revision + 1, 'status' => 'OFFERED', 'offered_at' => now(), 'confirmed_at' => null, 'change_note' => null]);
            $submission->save();
            $this->record($submission, $user, 'OFFERED', $submission->public_response, true);

            return $submission;
        });
    }

    public function requestChanges(CustomerPortalRequest $submission, User $user, int $revision, string $message): CustomerPortalRequest
    {
        return DB::transaction(function () use ($submission, $user, $revision, $message) {
            $submission = $this->locked($submission);
            $this->assertOwner($submission, $user);
            $this->assertRevision($submission, $revision);
            $this->assertStatus($submission, ['SUBMITTED', 'OFFERED', 'CONFIRMED']);
            $confirmed = $submission->status === 'CONFIRMED';
            $submission->update(['change_note' => $message, 'status' => $confirmed ? 'CONFIRMED' : 'CHANGES_REQUESTED']);
            $this->record($submission, $user, $confirmed ? 'AMENDMENT_REQUESTED' : 'CHANGES_REQUESTED', $message);

            return $submission;
        });
    }

    public function returnForChanges(CustomerPortalRequest $submission, User $user, int $revision, string $message): void
    {
        abort_unless(! $user->isCustomerAccount() && $user->can('orders.update'), 403);
        DB::transaction(function () use ($submission, $user, $revision, $message) {
            $submission = $this->locked($submission);
            $this->assertRevision($submission, $revision);
            $this->assertStatus($submission, ['SUBMITTED', 'OFFERED']);
            $submission->update(['status' => 'CHANGES_REQUESTED', 'public_response' => $message]);
            $this->record($submission, $user, 'CHANGES_REQUESTED', $message);
        });
    }

    public function confirm(CustomerPortalRequest $submission, User $user, int $revision): CustomerOrder
    {
        return DB::transaction(function () use ($submission, $user, $revision) {
            $submission = $this->locked($submission);
            $this->assertOwner($submission, $user);
            $this->assertRevision($submission, $revision);
            $this->assertStatus($submission, ['OFFERED']);
            $this->assertAmendable($submission);
            $channel = $submission->customer->defaultSalesChannel;
            if (! $channel?->is_active) {
                throw ValidationException::withMessages(['offer' => 'يرجى مراجعة خدمة العملاء لاستكمال قناة البيع.']);
            }
            $data = [
                'customer_id' => $submission->customer_id, 'sales_channel_id' => $channel->id,
                'customer_reference' => $submission->customer_reference, 'order_date' => now()->toDateString(),
                'requested_delivery_date' => $submission->promised_delivery_date?->toDateString(),
                'delivery_city' => $submission->delivery_city, 'delivery_address' => $submission->delivery_address,
                'commercial_notes' => $submission->customer_notes, 'payment_terms_type' => $submission->payment_terms_type,
                'deposit_required_amount' => $submission->deposit_required_amount,
                'payment_due_date' => $submission->payment_due_date?->toDateString(),
            ];
            if ($submission->customer_order_id) {
                $order = CustomerOrder::whereKey($submission->customer_order_id)->lockForUpdate()->firstOrFail();
                $order = $this->orders->updateOrder($order, $data, $submission->lines, $user, customerConfirmed: true);
                $order->update(Arr::only($data, ['delivery_city', 'delivery_address']));
            } else {
                $order = $this->orders->createOrder($data, $submission->lines, $user);
            }
            $this->orders->submitForProductionReview($order);
            $submission->update(['customer_order_id' => $order->id, 'status' => 'CONFIRMED', 'confirmed_at' => now(), 'change_note' => null]);
            $this->record($submission, $user, 'CONFIRMED', snapshot: true);

            return $order;
        });
    }

    protected function locked(CustomerPortalRequest $submission): CustomerPortalRequest
    {
        return CustomerPortalRequest::whereKey($submission->id)->lockForUpdate()->firstOrFail();
    }

    protected function assertOwner(CustomerPortalRequest $submission, User $user): void
    {
        abort_unless($user->hasRole('customer_portal') && $user->customer_id === $submission->customer_id && $user->customer?->is_active, 403);
    }

    protected function assertRevision(CustomerPortalRequest $submission, int $revision): void
    {
        if ($submission->revision !== $revision) {
            throw ValidationException::withMessages(['revision' => 'تم تحديث الطلب أو العرض. حدّث الصفحة وراجع النسخة الجديدة قبل المتابعة.']);
        }
    }

    /** @param list<string> $statuses */
    protected function assertStatus(CustomerPortalRequest $submission, array $statuses): void
    {
        if (! in_array($submission->status, $statuses, true)) {
            throw ValidationException::withMessages(['status' => 'هذا الإجراء غير متاح في حالة الطلب الحالية.']);
        }
    }

    protected function assertAmendable(CustomerPortalRequest $submission): void
    {
        $order = $submission->order;
        if ($order && ($order->status === 'CANCELLED' || $order->productionOrders()->exists() || $order->deliveryOrders()->exists())) {
            throw ValidationException::withMessages(['offer' => 'الطلب مرتبط بالتنفيذ أو ملغى. يجب معالجة التعديل مع مدير الإنتاج دون تغيير العرض المعتمد.']);
        }
    }

    /** @param array<int, array<string, mixed>> $lines
     * @return list<array<string, mixed>>
     */
    protected function prepareLines(array $lines, bool $priced, array $previousLines, array &$storedImages): array
    {
        $result = [];
        foreach ($lines as $line) {
            $data = Arr::only($line, ['line_type', 'product_model_id', 'product_configuration_id', 'custom_design', 'custom_design_name', 'requested_fabric', 'requested_color', 'material_id', 'requested_width_cm', 'requested_length_cm', 'has_storage', 'fabric_material_id', 'fabric_color_id', 'quantity', 'notes']);
            $previous = collect($previousLines)->firstWhere('line_key', $line['line_key'] ?? '') ?? [];
            $data['line_key'] = $previous['line_key'] ?? (string) Str::uuid();
            if ($priced) {
                $data['unit_price'] = $line['unit_price'];
                $data['discount_amount'] = $line['discount_amount'] ?? 0;
            }
            if ($data['line_type'] === 'READY_STOCK') {
                $material = Material::active()->sellableReady()->findOrFail($data['material_id']);
                $data = Arr::only($data, ['line_key', 'line_type', 'material_id', 'quantity', 'notes', 'unit_price', 'discount_amount']);
                $data['product_name'] = $material->sales_display_name ?: $material->name_ar;
            } else {
                $data['material_id'] = null;
                $custom = ! empty($data['custom_design']);
                if ($custom) {
                    $data['product_model_id'] = null;
                    $data['product_configuration_id'] = null;
                    $data['product_name'] = $data['custom_design_name'];
                    $data['custom_design_image_path'] = $previous['custom_design_image_path'] ?? null;
                    if (($line['custom_design_image'] ?? null) instanceof UploadedFile) {
                        $path = $line['custom_design_image']->store('customer-order-designs', 'local');
                        if ($path === false) {
                            throw ValidationException::withMessages(['image' => 'تعذر حفظ صورة التصميم.']);
                        }
                        $storedImages[] = $path;
                        $data['custom_design_image_path'] = $path;
                    }
                } else {
                    $data['product_name'] = ProductModel::whereKey($data['product_model_id'])->firstOrFail()->name_ar;
                }
                if ($priced) {
                    foreach (['requested_fabric', 'requested_color'] as $field) {
                        $data[$field] = $previous[$field] ?? $data[$field] ?? null;
                    }
                }
                $data['fabric_notes'] = ! empty($data['requested_fabric']) || ! empty($data['requested_color'])
                    ? 'قماش العميل: '.($data['requested_fabric'] ?? '').' — لون العميل: '.($data['requested_color'] ?? '')
                    : null;
                $fabric = Material::with('fabricSpec')->find($data['fabric_material_id'] ?? null);
                $color = FabricColor::find($data['fabric_color_id'] ?? null);
                $data['fabric_name'] = $fabric?->name_ar;
                $data['color_name'] = $color?->color_name_ar ?: $color?->color_code;
                if ($priced && (! empty($data['requested_fabric']) || ! empty($data['requested_color'])) && (! $fabric || ! $color)) {
                    throw ValidationException::withMessages(['fabric' => 'يجب ربط القماش واللون المطلوبين بمواد المخزون قبل إرسال العرض.']);
                }
                if ($priced && ! $custom) {
                    $configuration = ! empty($line['product_configuration_id'])
                        ? ProductConfiguration::find($line['product_configuration_id'])
                        : ProductConfiguration::where('product_model_id', $data['product_model_id'])->where('width_cm', $data['requested_width_cm'])->where('length_cm', $data['requested_length_cm'])->where('has_storage', ! empty($data['has_storage']))->where('is_active', true)->first();
                    if ($configuration) {
                        $data['product_configuration_id'] = $configuration->id;
                        $data['reference_width_cm'] = $configuration->width_cm;
                        $data['reference_length_cm'] = $configuration->length_cm;
                        $data['approved_recipe_version_id'] = ManufacturingRecipe::active()->where('product_configuration_id', $configuration->id)->with('currentApprovedVersion')->first()?->currentApprovedVersion?->id;
                    }
                }
            }
            $result[] = $data;
        }

        return $result;
    }

    protected function saveWithImages(callable $operation): CustomerPortalRequest
    {
        $storedImages = [];
        try {
            return DB::transaction(function () use ($operation, &$storedImages) {
                return $operation($storedImages);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedImages);
            throw $exception;
        }
    }

    protected function record(CustomerPortalRequest $submission, User $user, string $type, ?string $message = null, bool $snapshot = false): void
    {
        $submission->events()->create([
            'user_id' => $user->id, 'event_type' => $type, 'revision' => $submission->revision, 'message' => $message,
            'snapshot' => $snapshot ? Arr::only($submission->toArray(), ['lines', 'offer_total', 'promised_delivery_date', 'payment_terms_type', 'deposit_required_amount', 'payment_due_date', 'delivery_city', 'delivery_address', 'customer_reference']) : null,
        ]);
    }
}
