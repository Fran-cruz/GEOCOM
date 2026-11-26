<?php

namespace Tests\Feature;

use App\Models\Conductor;
use App\Models\Gasolinera;
use App\Models\Evidencia;
use App\Models\Orden;
use App\Models\Rol;
use App\Models\Solicitud;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebOperacionesTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'test-password';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Notification::fake();
    }

    public function test_admin_can_load_all_primary_web_indexes(): void
    {
        $this->loginAs($this->userWithRole('admin'));

        foreach ([
            '/dashboard',
            '/solicitudes',
            '/ordenes',
            '/conductores',
            '/vehiculos',
            '/gasolineras',
            '/caja-chica',
            '/reportes',
        ] as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_web_validation_messages_are_spanish_even_when_app_locale_is_english(): void
    {
        $this->loginAs($this->userWithRole('admin'));
        $vehiculo = Vehiculo::create([
            'placa' => 'VALID-'.strtoupper(Str::random(5)),
            'marca' => 'GEOCOM',
            'modelo' => 'Prueba',
        ]);

        $this->put("/vehiculos/{$vehiculo->id}", ['marca' => ''])
            ->assertSessionHasErrors([
                'marca' => 'El campo marca es obligatorio.',
            ]);
    }

    public function test_vehicle_creation_uses_api_rendimiento_validation_and_persists_it(): void
    {
        $this->loginAs($this->userWithRole('admin'));
        $datos = [
            'placa' => 'REND-'.strtoupper(Str::random(5)),
            'marca' => 'GEOCOM',
            'modelo' => 'Prueba',
        ];

        $this->post('/vehiculos', $datos)
            ->assertSessionHasErrors('rendimiento_km_litro');

        $this->post('/vehiculos', $datos + ['rendimiento_km_litro' => 12.5])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('vehiculos', [
            'placa' => $datos['placa'],
            'rendimiento_km_litro' => 12.5,
        ]);
    }

    public function test_order_detail_uses_the_authenticated_web_evidence_route(): void
    {
        $admin = $this->userWithRole('admin');
        $this->loginAs($admin);
        [$conductor, $vehiculo, $gasolinera] = $this->createOperationalCatalog();
        $solicitud = $this->createSolicitud($conductor, $vehiculo, 'Solicitud con evidencia');
        $orden = Orden::create([
            'numero_orden' => 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(5)),
            'solicitud_id' => $solicitud->id,
            'conductor_id' => $conductor->id,
            'gasolinera_id' => $gasolinera->id,
            'asignado_por' => $admin->id,
            'estado' => 'pendiente',
            'horas_vigencia' => 1,
        ]);
        $detalle = $orden->detalles()->create([
            'tipo_destino' => 'vehiculo',
            'vehiculo_id' => $vehiculo->id,
            'tipo_combustible' => 'diesel',
            'monto' => 100,
        ]);
        $evidencia = Evidencia::create([
            'detalle_orden_id' => $detalle->id,
            'tipo' => 'bomba',
            'ruta' => 'evidencias/prueba.jpg',
        ]);

        $this->get("/ordenes/{$orden->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ordenes/Show', false)
                ->where('orden.detalles.0.evidencias.0.url', route('web.evidencias.ver', $evidencia->id)));
    }

    public function test_admin_can_reject_and_approve_solicitudes_create_orders_and_register_caja_chica(): void
    {
        $admin = $this->userWithRole('admin');
        $this->loginAs($admin);

        [$conductor, $vehiculo, $gasolinera] = $this->createOperationalCatalog();

        $solicitudRechazada = $this->createSolicitud($conductor, $vehiculo, 'Solicitud para rechazo');
        $this->post("/solicitudes/{$solicitudRechazada->id}/rechazar")
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertDatabaseHas('solicitudes', [
            'id' => $solicitudRechazada->id,
            'estado' => 'rechazada',
        ]);

        $solicitudAprobada = $this->createSolicitud($conductor, $vehiculo, 'Solicitud para orden');
        $this->post('/ordenes', [
            'solicitud_id' => $solicitudAprobada->id,
            'gasolinera_id' => $gasolinera->id,
            'horas_vigencia' => 2,
            'detalles' => [[
                'tipo_destino' => 'vehiculo',
                'vehiculo_id' => $vehiculo->id,
                'monto' => 125.50,
            ]],
        ])->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('solicitudes', [
            'id' => $solicitudAprobada->id,
            'estado' => 'aprobada',
        ]);
        $this->assertDatabaseHas('ordenes', [
            'solicitud_id' => $solicitudAprobada->id,
            'conductor_id' => $conductor->id,
            'gasolinera_id' => $gasolinera->id,
            'asignado_por' => $admin->id,
            'estado' => 'pendiente',
        ]);

        $this->post('/caja-chica', [
            'conductor_id' => $conductor->id,
            'monto' => 300,
            'descripcion' => 'Entrega operativa de prueba',
            'fecha_entrega' => now()->toDateString(),
        ])->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('caja_chica', [
            'conductor_id' => $conductor->id,
            'registrado_por' => $admin->id,
            'monto' => 300,
            'descripcion' => 'Entrega operativa de prueba',
        ]);
    }

    public function test_supervisor_cannot_execute_any_web_write_action(): void
    {
        $this->loginAs($this->userWithRole('supervisor'));

        foreach ([
            ['POST', '/solicitudes/1/rechazar'],
            ['POST', '/ordenes'],
            ['POST', '/conductores'],
            ['PUT', '/conductores/1'],
            ['DELETE', '/conductores/1'],
            ['POST', '/vehiculos'],
            ['PUT', '/vehiculos/1'],
            ['DELETE', '/vehiculos/1'],
            ['POST', '/gasolineras'],
            ['PUT', '/gasolineras/1'],
            ['DELETE', '/gasolineras/1'],
            ['POST', '/caja-chica'],
        ] as [$method, $uri]) {
            $this->call($method, $uri)->assertForbidden();
        }
    }

    private function createOperationalCatalog(): array
    {
        $conductorUser = $this->userWithRole('conductor');
        $conductor = Conductor::create([
            'usuario_id' => $conductorUser->id,
            'telefono' => '555-'.random_int(1000, 9999),
            'cargo' => 'Operador',
        ]);
        $vehiculo = Vehiculo::create([
            'placa' => 'WEB-'.strtoupper(Str::random(6)),
            'marca' => 'GEOCOM',
            'modelo' => 'Prueba',
        ]);
        $gasolinera = Gasolinera::create([
            'nombre' => 'Gasolinera de prueba',
            'codigo_qr' => 'web-'.Str::uuid(),
        ]);

        return [$conductor, $vehiculo, $gasolinera];
    }

    private function createSolicitud(Conductor $conductor, Vehiculo $vehiculo, string $descripcion): Solicitud
    {
        return Solicitud::create([
            'conductor_id' => $conductor->id,
            'tipo_destino' => 'vehiculo',
            'vehiculo_id' => $vehiculo->id,
            'descripcion_actividad' => $descripcion,
        ]);
    }

    private function loginAs(User $user): void
    {
        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertRedirect('/dashboard')
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user, 'web');
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        $roleModel = Rol::firstOrCreate(
            ['nombre' => $role],
            ['etiqueta' => ucfirst($role)]
        );
        $user->roles()->attach($roleModel->id, ['activo' => true]);

        return $user;
    }
}
