<?php

namespace Tests\Feature;

use App\Models\Conductor;
use App\Models\Gasolinera;
use App\Models\Orden;
use App\Models\Rol;
use App\Models\Solicitud;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebReportesPdfTest extends TestCase
{
    use RefreshDatabase;

    private const ESTADOS_ORDEN = [
        'pendiente',
        'abierta',
        'abierta_tarde',
        'completada',
        'completada_tarde',
        'vencida',
        'cancelada',
    ];

    private const ESTADOS_EJECUTADOS = ['completada', 'completada_tarde'];

    private const PASSWORD = 'test-password';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Notification::fake();
    }

    public function test_dashboard_and_report_totals_include_late_completed_orders_and_all_states(): void
    {
        $admin = $this->userWithRole('admin');
        $catalogos = $this->createOrdersForReport($admin);
        $this->actingAs($admin, 'web');

        $dashboard = $this->get('/dashboard');
        $conteosEsperados = DB::table('ordenes')
            ->select('estado', DB::raw('COUNT(*) as cantidad'))
            ->groupBy('estado')
            ->pluck('cantidad', 'estado')
            ->map(fn ($cantidad) => (int) $cantidad)
            ->all();
        $conteosEsperados = array_replace(array_fill_keys(self::ESTADOS_ORDEN, 0), $conteosEsperados);
        $ordenesEjecutadasEsperadas = $conteosEsperados['completada'] + $conteosEsperados['completada_tarde'];
        $gastoEjecutadoEsperado = (float) DB::table('detalle_ordenes')
            ->join('ordenes', 'ordenes.id', '=', 'detalle_ordenes.orden_id')
            ->whereIn('ordenes.estado', self::ESTADOS_EJECUTADOS)
            ->sum('detalle_ordenes.monto');

        $dashboard->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index', false)
            ->where('ordenesPorEstado', $conteosEsperados)
            ->where('ordenesEjecutadas', $ordenesEjecutadasEsperadas)
            ->where('gastoEjecutado', $gastoEjecutadoEsperado)
            ->where('gastoCompletado', $gastoEjecutadoEsperado));

        $filters = [
            'desde' => now()->subDays(2)->toDateString(),
            'hasta' => now()->toDateString(),
            'estado' => 'completada_tarde',
            'vehiculo_id' => $catalogos['vehiculo']->id,
            'conductor_id' => $catalogos['conductor']->id,
            'gasolinera_id' => $catalogos['gasolinera']->id,
        ];

        $this->get('/reportes?'.http_build_query($filters))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Reportes/Index', false)
                ->where('resumen.total', 1)
                ->where('resumen.por_estado', [
                    'pendiente' => 0,
                    'abierta' => 0,
                    'abierta_tarde' => 0,
                    'completada' => 0,
                    'completada_tarde' => 1,
                    'vencida' => 0,
                    'cancelada' => 0,
                ])
                ->where('resumen.ordenes_ejecutadas', 1)
                ->where('resumen.monto_consumido', 250)
                ->where('export_pdf_url', route('web.reportes.pdf', $filters)));
    }

    public function test_admin_supervisor_and_contabilidad_can_download_filtered_pdf(): void
    {
        $admin = $this->userWithRole('admin');
        $catalogos = $this->createOrdersForReport($admin);
        $filters = [
            'desde' => now()->subDays(2)->toDateString(),
            'hasta' => now()->toDateString(),
            'estado' => 'completada_tarde',
            'vehiculo_id' => $catalogos['vehiculo']->id,
            'conductor_id' => $catalogos['conductor']->id,
            'gasolinera_id' => $catalogos['gasolinera']->id,
        ];

        foreach (['admin', 'supervisor', 'contabilidad'] as $role) {
            $user = $role === 'admin' ? $admin : $this->userWithRole($role);

            $this->from('/login')->post('/login', [
                'email' => $user->email,
                'password' => self::PASSWORD,
            ])->assertRedirect('/dashboard');

            $response = $this->get(route('web.reportes.pdf', $filters));

            $response->assertOk()
                ->assertHeader('Content-Type', 'application/pdf');
            $this->assertNotEmpty($response->getContent());
            $this->assertStringStartsWith('%PDF-', $response->getContent());

            $this->post('/logout')->assertRedirect('/login');
        }
    }

    public function test_pdf_redirects_guests_and_forbids_conductores(): void
    {
        $this->get(route('web.reportes.pdf'))->assertRedirect('/login');

        $conductor = $this->userWithRole('conductor');
        $this->actingAs($conductor, 'web')
            ->get(route('web.reportes.pdf'))
            ->assertForbidden();
    }

    private function createOrdersForReport(User $admin): array
    {
        $catalogos = $this->createReportCatalog();

        return $this->createOrderFixtures($admin, $catalogos);
    }

    private function createReportCatalog(): array
    {
        $user = $this->userWithRole('conductor');
        $conductor = Conductor::create([
            'usuario_id' => $user->id,
            'telefono' => '555-0199',
            'cargo' => 'Operador',
        ]);
        $vehiculo = Vehiculo::create([
            'placa' => 'PDF-100',
            'marca' => 'GEOCOM',
            'modelo' => 'Prueba',
        ]);
        $gasolinera = Gasolinera::create([
            'nombre' => 'Estación de prueba',
            'codigo_qr' => 'pdf-report-qr',
        ]);

        return compact('conductor', 'vehiculo', 'gasolinera');
    }

    private function createOrderFixtures(User $admin, array $catalogos): array
    {
        $montos = [
            'pendiente' => 10,
            'abierta' => 20,
            'abierta_tarde' => 30,
            'completada' => 100,
            'completada_tarde' => 250,
            'vencida' => 40,
        ];

        $numero = Orden::count() + 1;
        foreach ($montos as $indice => $monto) {
            $solicitud = Solicitud::create([
                'conductor_id' => $catalogos['conductor']->id,
                'tipo_destino' => 'vehiculo',
                'vehiculo_id' => $catalogos['vehiculo']->id,
                'descripcion_actividad' => "Solicitud de reporte {$indice}",
                'estado' => 'aprobada',
            ]);
            $fechaAsignacion = now()->subDay();
            $orden = Orden::create([
                'numero_orden' => 'ORD-'.$fechaAsignacion->format('Ymd').'-'.str_pad((string) $numero++, 5, '0', STR_PAD_LEFT),
                'solicitud_id' => $solicitud->id,
                'conductor_id' => $catalogos['conductor']->id,
                'gasolinera_id' => $catalogos['gasolinera']->id,
                'asignado_por' => $admin->id,
                'estado' => $indice,
                'horas_vigencia' => 1,
                'fecha_asignacion' => $fechaAsignacion,
                'fecha_completada' => in_array($indice, self::ESTADOS_EJECUTADOS, true) ? $fechaAsignacion : null,
            ]);

            $orden->detalles()->create([
                'tipo_destino' => 'vehiculo',
                'vehiculo_id' => $catalogos['vehiculo']->id,
                'tipo_combustible' => 'diesel',
                'monto' => $monto,
            ]);
        }

        return $catalogos;
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
