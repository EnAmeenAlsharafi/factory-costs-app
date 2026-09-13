<?php

namespace App\Services;

use App\Models\CustomerProductAlias;
use App\Models\ProductConfiguration;
use App\Models\ProductModel;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    public function createModel(array $data, ?UploadedFile $image = null): ProductModel
    {
        return DB::transaction(function () use ($data, $image) {
            $modelCode = DocumentNumberService::generateModelCode();

            $imageSource = $data['image_source'] ?? 'file';
            $imagePath = null;

            if ($imageSource === 'url' && ! empty($data['reference_image_url'])) {
                $imagePath = trim($data['reference_image_url']);
            } elseif ($image) {
                $imagePath = $image->store('product_models', 'public');
            }

            return ProductModel::create([
                'model_code' => $modelCode,
                'name_ar' => $data['name_ar'],
                'name_en' => $data['name_en'] ?? null,
                'description' => $data['description'] ?? null,
                'reference_image_path' => $imagePath,
                'design_notes' => $data['design_notes'] ?? null,
                'is_custom_template' => $data['is_custom_template'] ?? false,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function updateModel(ProductModel $model, array $data, ?UploadedFile $image = null): ProductModel
    {
        return DB::transaction(function () use ($model, $data, $image) {
            $imageSource = $data['image_source'] ?? 'file';

            if ($imageSource === 'url' && ! empty($data['reference_image_url'])) {
                if ($model->reference_image_path && ! str_starts_with($model->reference_image_path, 'http://') && ! str_starts_with($model->reference_image_path, 'https://') && Storage::disk('public')->exists($model->reference_image_path)) {
                    Storage::disk('public')->delete($model->reference_image_path);
                }
                $data['reference_image_path'] = trim($data['reference_image_url']);
            } elseif ($imageSource === 'file' && $image) {
                if ($model->reference_image_path && ! str_starts_with($model->reference_image_path, 'http://') && ! str_starts_with($model->reference_image_path, 'https://') && Storage::disk('public')->exists($model->reference_image_path)) {
                    Storage::disk('public')->delete($model->reference_image_path);
                }
                $data['reference_image_path'] = $image->store('product_models', 'public');
            }

            $model->update($data);

            return $model;
        });
    }

    public function createConfiguration(ProductModel $model, array $data): ProductConfiguration
    {
        return DB::transaction(function () use ($model, $data) {
            $width = (float) $data['width_cm'];
            $length = (float) $data['length_cm'];
            $hasStorage = (bool) ($data['has_storage'] ?? false);

            // Prevent duplicate configuration under the same model
            $exists = ProductConfiguration::where('product_model_id', $model->id)
                ->where('width_cm', $width)
                ->where('length_cm', $length)
                ->where('has_storage', $hasStorage)
                ->exists();

            if ($exists) {
                throw new Exception('تكوين التصنيع هذا (نفس المقاس وخيار التخزين) موجود بالفعل لهذا الموديل.');
            }

            $cfgCode = DocumentNumberService::generateConfigurationCode();

            return ProductConfiguration::create([
                'configuration_code' => $cfgCode,
                'product_model_id' => $model->id,
                'standard_bed_size_id' => $data['standard_bed_size_id'] ?? null,
                'width_cm' => $width,
                'length_cm' => $length,
                'has_storage' => $hasStorage,
                'configuration_name' => $data['configuration_name'] ?? null,
                'is_standard' => $data['is_standard'] ?? true,
                'is_active' => $data['is_active'] ?? true,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    public function createAlias(array $data): CustomerProductAlias
    {
        return DB::transaction(function () use ($data) {
            $customerId = $data['customer_id'];
            $modelId = $data['product_model_id'];
            $isDefault = (bool) ($data['is_default'] ?? false);
            $customerCode = ! empty($data['customer_product_code']) ? $data['customer_product_code'] : null;

            if ($customerCode) {
                $exists = CustomerProductAlias::where('customer_id', $customerId)
                    ->where('customer_product_code', $customerCode)
                    ->exists();

                if ($exists) {
                    throw new Exception("رمز المنتج ({$customerCode}) مستخدم بالفعل لاسم هذا العميل.");
                }
            }

            if ($isDefault) {
                CustomerProductAlias::where('customer_id', $customerId)
                    ->where('product_model_id', $modelId)
                    ->update(['is_default' => false]);
            }

            return CustomerProductAlias::create([
                'customer_id' => $customerId,
                'product_model_id' => $modelId,
                'customer_product_name' => $data['customer_product_name'],
                'customer_product_code' => $customerCode,
                'description' => $data['description'] ?? null,
                'is_default' => $isDefault,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });
    }

    public function setDefaultAlias(CustomerProductAlias $alias): void
    {
        DB::transaction(function () use ($alias) {
            CustomerProductAlias::where('customer_id', $alias->customer_id)
                ->where('product_model_id', $alias->product_model_id)
                ->update(['is_default' => false]);

            $alias->update(['is_default' => true]);
        });
    }
}
