<?php

namespace Tests\Feature;

use App\Models\Conductor;
use App\Models\Gasolinera;
use App\Models\Rol;
use App\Models\Solicitud;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'test-password';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Notification::fake();
    }

    public function test_admin_can_login_read_and_perform_web_writes(): void
    {
        $admin = $this->userWithRole('admin');
        $this->loginAs($admin);

        $this->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index', false)
                ->where('auth.user.id', $admin->id)
                ->where('auth.user.name', $admin->name)
                ->where('auth.user.email', $admin->email)
                ->where('auth.user.rol', 'admin')
                ->where('auth.can_operate', true)
                ->where('auth.can_consult', true));

        foreach ([
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

        $this->post('/conductores', [
            'nombre' => 'Conductor de prueba',
            'correo' => 'conductor-web@example.test',
            'contrasena' => self::PASSWORD,
            'telefono' => '555-0101',
            'cargo' => 'Operador',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['email' => 'conductor-web@example.test']);

        $vehiculo = Vehiculo::create([
            'placa' => 'WEB-100',
            'marca' => 'Marca',
            'modelo' => 'Modelo',
        ]);
        $this->put("/vehiculos/{$vehiculo->id}", ['marca' => 'Marca actualizada'])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('vehiculos', ['id' => $vehiculo->id, 'marca' => 'Marca actualizada']);

        $gasolinera = Gasolinera::create([
            'nombre' => 'Gasolinera de prueba',
            'codigo_qr' => 'web-test-qr',
        ]);
        $this->delete("/gasolineras/{$gasolinera->id}")
            ->assertSessionHas('success');
        $this->assertDatabaseHas('gasolineras', ['id' => $gasolinera->id, 'activo' => false]);

        $this->post('/caja-chica', [
            'monto' => 100,
            'descripcion' => 'Entrega de prueba',
            'fecha_entrega' => now()->toDateString(),
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('caja_chica', ['registrado_por' => $admin->id, 'descripcion' => 'Entrega de prueba']);

        $conductor = $this->userWithRole('conductor');
        $ficha = Conductor::create([
            'usuario_id' => $conductor->id,
            'telefono' => '555-0102',
            'cargo' => 'Operador',
        ]);
        $solicitudRechazo = Solicitud::create([
            'conductor_id' => $ficha->id,
            'tipo_destino' => 'vehiculo',
            'descripcion_actividad' => 'Solicitud para rechazo',
        ]);
        $this->post("/solicitudes/{$solicitudRechazo->id}/rechazar")
            ->assertSessionHas('success');
        $this->assertDatabaseHas('solicitudes', ['id' => $solicitudRechazo->id, 'estado' => 'rechazada']);

        $vehiculoOrden = Vehiculo::create([
            'placa' => 'WEB-200',
            'marca' => 'Marca',
            'modelo' => 'Modelo',
        ]);
        $gasolineraOrden = Gasolinera::create([
            'nombre' => 'Gasolinera para orden',
            'codigo_qr' => 'web-order-qr',
        ]);
        $solicitudOrden = Solicitud::create([
            'conductor_id' => $ficha->id,
            'tipo_destino' => 'vehiculo',
            'vehiculo_id' => $vehiculoOrden->id,
            'descripcion_actividad' => 'Solicitud para orden',
        ]);

        $this->post('/ordenes', [
            'solicitud_id' => $solicitudOrden->id,
            'gasolinera_id' => $gasolineraOrden->id,
            'horas_vigencia' => 2,
            'detalles' => [[
                'tipo_destino' => 'vehiculo',
                'vehiculo_id' => $vehiculoOrden->id,
                'monto' => 100,
            ]],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('solicitudes', ['id' => $solicitudOrden->id, 'estado' => 'aprobada']);
        $this->assertDatabaseHas('ordenes', ['solicitud_id' => $solicitudOrden->id, 'asignado_por' => $admin->id]);
    }

    public function test_supervisor_can_login_and_read_but_cannot_write(): void
    {
        $this->assertReadOnlyRoleAccess('supervisor');
    }

    public function test_contabilidad_can_login_and_read_but_cannot_write(): void
    {
        $this->assertReadOnlyRoleAccess('contabilidad');
    }

    public function test_conductor_is_logged_out_when_attempting_web_login(): void
    {
        $conductor = $this->userWithRole('conductor');

        $this->from('/login')->post('/login', [
            'email' => $conductor->email,
            'password' => self::PASSWORD,
        ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_inactive_admin_role_does_not_authorize_web_login(): void
    {
        $admin = $this->userWithRole('admin', active: false);

        $this->from('/login')->post('/login', [
            'email' => $admin->email,
            'password' => self::PASSWORD,
        ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    private function assertReadOnlyRoleAccess(string $role): void
    {
        $user = $this->userWithRole($role);
        $this->loginAs($user);

        $this->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index', false)
                ->where('auth.user.rol', $role)
                ->where('auth.can_operate', false)
                ->where('auth.can_consult', true));

        foreach ([
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

    private function loginAs(User $user): void
    {
        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertRedirect('/dashboard')
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user, 'web');
    }

    private function userWithRole(string $role, bool $active = true): User
    {
        $usuario = User::factory()->create(['password' => self::PASSWORD]);
        $rol = Rol::firstOrCreate(
            ['nombre' => $role],
            ['etiqueta' => ucfirst($role)]
        );
        $usuario->roles()->attach($rol->id, ['activo' => $active]);

        return $usuario;
    }
}
