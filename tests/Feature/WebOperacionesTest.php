<?php

namespace Tests\Feature;

use App\Models\Conductor;
use App\Models\Combustible;
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
        Combustible::create([
            'combustible' => 'diesel',
            'ciudad' => 'san_pedro_sula',
            'precio' => 50,
            'variacion' => 0,
            'vigente_desde' => now()->toDateString(),
            'estado' => 'vigente',
            'fuente' => 'test',
        ]);
        $evidencia = Evidencia::create([
            'detalle_orden_id' => $detalle->id,
            'tipo' => 'bomba',
            'ruta' => 'evidencias/prueba.jpg',
        ]);

        $this->get("/ordenes/{$orden->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ordenes/Show', false)
                ->where('orden.detalles.0.evidencias.0.url', route('web.evidencias.ver', $evidencia->id))
                ->where('orden.detalles.0.combustible_precio', 'diesel')
                ->where('orden.detalles.0.precio_galon', 50)
                ->where('orden.detalles.0.galones_estimados', 2)
                ->where('orden.detalles.0.litros_estimados', 7.57)
                ->where('orden.detalles.0.km_estimados', 60.57));
    }

    public function test_admin_can_reject_solicitudes_and_create_orders(): void
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

    }

    public function test_caja_chica_routes_are_removed(): void
    {
        $this->get('/caja-chica')->assertNotFound();
        $this->post('/caja-chica')->assertNotFound();
        $this->getJson('/api/caja-chica')->assertNotFound();
        $this->postJson('/api/caja-chica')->assertNotFound();
    }

    public function test_admin_can_estimate_fuel_using_the_api_calculation_and_default_city(): void
    {
        $this->loginAs($this->userWithRole('admin'));
        $vehiculo = Vehiculo::create([
            'placa' => 'EST-'.strtoupper(Str::random(5)),
            'marca' => 'GEOCOM',
            'modelo' => 'Prueba',
            'tipo_combustible' => 'diesel',
            'rendimiento_km_litro' => 10,
        ]);
        Combustible::create([
            'combustible' => 'diesel',
            'ciudad' => 'san_pedro_sula',
            'precio' => 50,
            'variacion' => 0,
            'vigente_desde' => now()->toDateString(),
            'estado' => 'vigente',
            'fuente' => 'test',
        ]);

        $this->postJson('/ordenes/estimar', [
            'monto' => 100,
            'vehiculo_id' => $vehiculo->id,
        ])->assertOk()
            ->assertJsonPath('monto_autorizado', 100)
            ->assertJsonPath('combustible', 'diesel')
            ->assertJsonPath('ciudad', 'san_pedro_sula')
            ->assertJsonPath('precio_por_galon', 50)
            ->assertJsonPath('galones_aproximados', 2)
            ->assertJsonPath('litros_aproximados', 7.57)
            ->assertJsonPath('rendimiento_km_litro', 10)
            ->assertJsonPath('kilometros_aproximados', 75.71);
    }

    public function test_admin_can_rehabilitate_expired_order_after_password_confirmation(): void
    {
        $admin = $this->userWithRole('admin');
        $this->loginAs($admin);
        [$conductor, $vehiculo, $gasolinera] = $this->createOperationalCatalog();
        $solicitud = $this->createSolicitud($conductor, $vehiculo, 'Solicitud vencida');
        $orden = Orden::create([
            'numero_orden' => 'ORD-'.now()->format('Ymd').'-'.strtoupper(Str::random(5)),
            'solicitud_id' => $solicitud->id,
            'conductor_id' => $conductor->id,
            'gasolinera_id' => $gasolinera->id,
            'asignado_por' => $admin->id,
            'estado' => 'vencida',
            'horas_vigencia' => 1,
        ]);

        $this->from("/ordenes/{$orden->id}")->post("/ordenes/{$orden->id}/rehabilitar", [
            'horas_vigencia' => 2,
            'password' => 'incorrecta',
        ])->assertRedirect("/ordenes/{$orden->id}")
            ->assertSessionHasErrors('password');

        $this->from("/ordenes/{$orden->id}")->post("/ordenes/{$orden->id}/rehabilitar", [
            'horas_vigencia' => 2,
            'password' => self::PASSWORD,
        ])->assertRedirect("/ordenes/{$orden->id}")
            ->assertSessionHas('success', 'La orden fue rehabilitada correctamente.');

        $this->assertDatabaseHas('ordenes', [
            'id' => $orden->id,
            'estado' => 'abierta_tarde',
            'horas_vigencia' => 2,
            'asignado_por' => $admin->id,
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
