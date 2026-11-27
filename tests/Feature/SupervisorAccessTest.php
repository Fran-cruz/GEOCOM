<?php

namespace Tests\Feature;

use App\Models\Combustible;
use App\Models\Rol;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupervisorAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_view_admin_data_but_cannot_modify_it(): void
    {
        Sanctum::actingAs($this->userWithRole('supervisor'));

        foreach ([
            '/api/solicitudes',
            '/api/ordenes',
            '/api/conductores',
            '/api/vehiculos',
            '/api/gasolineras',
            '/api/reportes/resumen',
            '/api/reportes/por-conductor',
            '/api/reportes/por-gasolinera',
        ] as $path) {
            $this->getJson($path)->assertOk();
        }

        $activeVehicles = $this->getJson('/api/vehiculos')->assertOk()->json('total');
        $allVehicles = $this->getJson('/api/vehiculos?incluir_inactivos=1')->assertOk()->json('total');
        $this->assertSame($activeVehicles, $allVehicles);

        $this->postJson('/api/gasolineras', [
            'nombre' => 'Estación de prueba',
            'codigo_qr' => 'solo-admin-qr',
        ])->assertForbidden();
    }

    public function test_supervisor_can_read_weekly_fuel_price_history(): void
    {
        Sanctum::actingAs($this->userWithRole('supervisor'));

        \App\Models\Combustible::create([
            'combustible' => 'regular',
            'ciudad' => 'san_pedro_sula',
            'precio' => 98.50,
            'variacion' => 1.25,
            'vigente_desde' => now()->toDateString(),
            'estado' => 'vigente',
            'fuente' => 'test',
        ]);
        $this->assertDatabaseHas('combustibles', [
            'combustible' => 'regular',
            'ciudad' => 'san_pedro_sula',
            'precio' => 98.50,
        ]);

        $this->getJson('/api/combustibles/historial')
            ->assertOk()
            ->assertJsonPath('ciudad', 'san_pedro_sula')
            ->assertJsonCount(1, 'semanas')
            ->assertJsonPath('semanas.0.regular', 98.5)
            ->assertJsonPath('semanas.0.vigente_desde', now()->toDateString());

        $this->getJson('/api/combustibles/historial?ciudad=san_pedro_sula&semanas=1')
            ->assertOk()
            ->assertJsonCount(1, 'semanas');
    }

    public function test_admin_can_still_modify_catalogs(): void
    {
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->postJson('/api/gasolineras', [
            'nombre' => 'Estación de prueba',
            'codigo_qr' => 'admin-qr-test',
        ])->assertCreated();
    }

    public function test_mobile_order_estimation_keeps_its_api_response_contract(): void
    {
        Sanctum::actingAs($this->userWithRole('admin'));
        $vehiculo = Vehiculo::create([
            'placa' => 'API-EST-1',
            'marca' => 'GEOCOM',
            'modelo' => 'Prueba',
            'tipo_combustible' => 'diesel',
            'rendimiento_km_litro' => 10,
        ]);
        Combustible::create([
            'combustible' => 'diesel',
            'ciudad' => 'tegucigalpa',
            'precio' => 60,
            'variacion' => 0,
            'vigente_desde' => now()->toDateString(),
            'estado' => 'vigente',
            'fuente' => 'test',
        ]);

        $this->postJson('/api/ordenes/estimar', [
            'monto' => 120,
            'vehiculo_id' => $vehiculo->id,
            'ciudad' => 'tegucigalpa',
        ])->assertOk()
            ->assertJsonPath('monto_autorizado', 120)
            ->assertJsonPath('combustible', 'diesel')
            ->assertJsonPath('precio_por_galon', 60)
            ->assertJsonPath('galones_aproximados', 2)
            ->assertJsonPath('litros_aproximados', 7.57)
            ->assertJsonPath('rendimiento_km_litro', 10)
            ->assertJsonPath('kilometros_aproximados', 75.71);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(
            Rol::where('nombre', $role)->value('id'),
            ['activo' => true]
        );

        return $user;
    }
}
