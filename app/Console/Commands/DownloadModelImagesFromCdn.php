<?php

namespace App\Console\Commands;

use App\Models\ProductModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class DownloadModelImagesFromCdn extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:download-model-images {--force : Re-download even if already downloaded}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download external product model reference images (e.g. Salla CDN) into local public storage';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Storage::disk('public')->makeDirectory('product_models');

        $query = ProductModel::query();
        if (! $this->option('force')) {
            $query->where(function ($q): void {
                $q->where('reference_image_path', 'like', 'http://%')
                    ->orWhere('reference_image_path', 'like', 'https://%');
            });
        }

        $models = $query->get();
        $this->info("Found {$models->count()} product models to process.");

        if ($models->isEmpty()) {
            return self::SUCCESS;
        }

        $downloaded = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($models as $model) {
            $rawPath = (string) $model->reference_image_path;

            if (empty($rawPath)) {
                $skipped++;

                continue;
            }

            if (! str_starts_with($rawPath, 'http://') && ! str_starts_with($rawPath, 'https://')) {
                // If it's already a local path, ensure the file exists
                if (Storage::disk('public')->exists($rawPath)) {
                    $skipped++;

                    continue;
                }
                $this->warn("Model #{$model->id} has local path '{$rawPath}' but file does not exist on disk.");
                $skipped++;

                continue;
            }

            $url = $rawPath;
            $pathParts = parse_url($url, PHP_URL_PATH) ?? '';
            $extension = strtolower(pathinfo($pathParts, PATHINFO_EXTENSION));
            if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                $extension = 'jpg';
            }

            $hash = substr(md5($url), 0, 10);
            $filename = "model_{$model->id}_{$hash}.{$extension}";
            $localRelativePath = "product_models/{$filename}";

            $this->line("Downloading #{$model->id} ({$model->name_ar})...");

            try {
                $response = Http::withoutVerifying()
                    ->timeout(30)
                    ->retry(2, 500)
                    ->get($url);

                if ($response->successful()) {
                    Storage::disk('public')->put($localRelativePath, $response->body());
                    $model->update(['reference_image_path' => $localRelativePath]);
                    $downloaded++;
                    $this->info("  -> Saved: {$localRelativePath}");
                } else {
                    $failed++;
                    $this->error("  -> HTTP {$response->status()} for model #{$model->id}");
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  -> Error downloading #{$model->id}: ".$e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Completed: {$downloaded} downloaded, {$skipped} skipped, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
