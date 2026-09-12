<?php

namespace Tests\Feature\MasterData;

use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitOfMeasureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_view_units_list_and_seeds_exist(): void
    {
        $admin = User::where('username', 'admin')->first();

        $response = $this->actingAs($admin)->get(route('units.index'));

        $response->assertStatus(200);
        $response->assertViewIs('units.index');
        $response->assertSee('METER');
        $response->assertSee('BOARD');
        $response->assertSee('PIECE');
        $response->assertSee('KG');
    }

    public function test_admin_can_create_unit_with_decimal_precision(): void
    {
        $admin = User::where('username', 'admin')->first();

        $response = $this->actingAs($admin)->post(route('units.store'), [
            'code' => 'CM',
            'name_ar' => 'سنتيمتر',
            'name_en' => 'Centimeter',
            'symbol' => 'سم',
            'unit_type' => 'LENGTH',
            'allows_decimal' => 1,
            'decimal_precision' => 2,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('units.index'));
        $this->assertDatabaseHas('units_of_measure', [
            'code' => 'CM',
            'name_ar' => 'سنتيمتر',
            'allows_decimal' => true,
            'decimal_precision' => 2,
        ]);
    }

    public function test_admin_can_update_unit_of_measure(): void
    {
        $admin = User::where('username', 'admin')->first();
        $unit = UnitOfMeasure::where('code', 'PIECE')->first();

        $response = $this->actingAs($admin)->put(route('units.update', $unit), [
            'code' => 'PIECE',
            'name_ar' => 'قطعة / وحدة مفردة',
            'name_en' => 'Piece',
            'symbol' => 'قطعة',
            'unit_type' => 'COUNT',
            'allows_decimal' => 0,
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('units.index'));
        $this->assertDatabaseHas('units_of_measure', [
            'id' => $unit->id,
            'name_ar' => 'قطعة / وحدة مفردة',
            'allows_decimal' => false,
            'decimal_precision' => 0,
        ]);
    }

    public function test_admin_can_toggle_unit_status(): void
    {
        $admin = User::where('username', 'admin')->first();
        $unit = UnitOfMeasure::where('code', 'ROLL')->first();

        $this->assertTrue($unit->is_active);

        // Deactivate
        $this->actingAs($admin)->post(route('units.toggle-status', $unit));
        $this->assertFalse($unit->fresh()->is_active);

        // Reactivate
        $this->actingAs($admin)->post(route('units.toggle-status', $unit));
        $this->assertTrue($unit->fresh()->is_active);
    }
}
