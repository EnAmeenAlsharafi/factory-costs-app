<?php

namespace Tests\Feature;

use App\Models\FabricMaterialSpec;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_catalogue_cover_is_served_from_storage_and_shown_on_the_material_page(): void
    {
        $this->seed();
        Storage::fake('public');
        $path = UploadedFile::fake()->image('cover.jpg')->store('fabric-catalogs', 'public');
        $material = Material::factory()->create(['material_category_id' => MaterialCategory::where('code', 'FABRIC')->value('id')]);
        $spec = FabricMaterialSpec::create(['material_id' => $material->id, 'catalog_number' => '3020', 'fabric_type' => 'شانيل', 'width_cm' => 140, 'catalog_image_path' => $path]);

        $this->get(route('public-file', ['path' => $path]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs(User::where('username', 'admin')->firstOrFail())
            ->get(route('materials.show', $spec->material_id))
            ->assertOk()
            ->assertSee(route('public-file', ['path' => $path]), false);
    }

    public function test_only_upload_folders_are_served(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('private/secret.txt', 'x');
        Storage::disk('public')->put('fabric-catalogs/cover.jpg', 'x');

        $this->get('/files/private/secret.txt')->assertNotFound();
        $this->get('/files/fabric-catalogs/../private/secret.txt')->assertNotFound();
        $this->get('/files/fabric-catalogs/missing.jpg')->assertNotFound();
    }
}
