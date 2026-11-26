<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
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
            '/api/caja-chica',
            '/api/reportes/resumen',
            '/api/reportes/por-conductor',
            '/api/reportes/por-gasolinera',
        ] as $path) {
            $this->getJson($path)->assertOk();
        }

        $activeVehicles = $this->getJson('/api/vehiculos')->assertOk()->json('total');
        $allVehicles = $this->getJson('/api/vehiculos?incluir_inactivos=1')->assertOk()->json('total');
        $this->assertGreaterThan($activeVehicles, $allVehicles);

        $this->postJson('/api/gasolineras', [
            'nombre' => 'Estación de prueba',
            'codigo_qr' => 'solo-admin-qr',
        ])->assertForbidden();
    }

    public function test_admin_can_still_modify_catalogs(): void
    {
        Sanctum::actingAs($this->userWithRole('admin'));

        $this->postJson('/api/gasolineras', [
            'nombre' => 'Estación de prueba',
            'codigo_qr' => 'admin-qr-test',
        ])->assertCreated();
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
