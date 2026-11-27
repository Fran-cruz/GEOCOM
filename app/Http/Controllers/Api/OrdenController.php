<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{DetalleOrden, Evidencia, Gasolinera, Orden, Solicitud};
use App\Models\Vehiculo;
use App\Notifications\{OrdenAsignada, OrdenCompletada};
use App\Services\CombustibleEstimator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OrdenController extends Controller
{
    // Consulta con filtros (estado, conductor, gasolinera, fechas)
    public function index(Request $request)
    {
        Orden::marcarVencidas(); // respaldo: actualiza vencimientos antes de listar

        // when() aplica cada filtro solo si viene en la petición
        $consulta = Orden::with(['conductor.usuario:id,name', 'gasolinera', 'detalles.vehiculo', 'detalles.evidencias'])
            ->when($request->estado, function ($q, $v) {
                $estados = array_filter(array_map('trim', explode(',', $v)));

                return $q->whereIn('estado', $estados);
            })
            ->when($request->conductor_id,  fn ($q, $v) => $q->where('conductor_id', $v))
            ->when($request->gasolinera_id, fn ($q, $v) => $q->where('gasolinera_id', $v))
            ->when($request->desde,         fn ($q, $v) => $q->whereDate('fecha_asignacion', '>=', $v))
            ->when($request->hasta,         fn ($q, $v) => $q->whereDate('fecha_asignacion', '<=', $v))
            ->latest('fecha_asignacion');

        // El conductor solo ve las suyas, sin vencidas, y las pendientes en versión reducida
        if ($conductor = $request->user()->conductor) {
            $consulta->where('conductor_id', $conductor->id)->where('estado', '!=', 'vencida');

            return $consulta->paginate(15)->through(fn ($orden) => $this->presentarParaConductor($orden));
        }

        return $consulta->paginate(15);
    }

    // El admin asigna los montos y se genera la orden
    public function store(Request $request)
    {
        $datos = $request->validate([
            'solicitud_id'               => 'required|exists:solicitudes,id',
            'gasolinera_id'              => 'required|exists:gasolineras,id',
            'horas_vigencia'             => 'required|integer|between:1,3',
            'detalles'                   => 'required|array|min:1|max:2', // máx. una línea de vehículo y una de MGP
            'detalles.*.tipo_destino'    => 'required|in:vehiculo,mgp',
            'detalles.*.vehiculo_id'     => 'required_if:detalles.*.tipo_destino,vehiculo|nullable|exists:vehiculos,id',
            'detalles.*.descripcion_mgp' => 'required_if:detalles.*.tipo_destino,mgp|nullable|string|max:150',
            'detalles.*.monto'           => 'required|numeric|min:0.01|max:99999999.99',
        ]);

        $solicitud = Solicitud::findOrFail($datos['solicitud_id']);
        abort_unless($solicitud->estado === 'pendiente', 422, 'La solicitud ya fue procesada.');

        // Transacción: orden + líneas + cambio de estado de la solicitud, o nada
        $orden = DB::transaction(function () use ($datos, $solicitud, $request) {
            $ahora = now();

            $orden = Orden::create([
                // Ej.: ORD-20261110-A1B2C
                'numero_orden'     => 'ORD-' . $ahora->format('Ymd') . '-' . strtoupper(Str::random(5)),
                'solicitud_id'     => $solicitud->id,
                'conductor_id'     => $solicitud->conductor_id,
                'gasolinera_id'    => $datos['gasolinera_id'],
                'asignado_por'     => $request->user()->id,
                'estado'           => 'pendiente',
                'horas_vigencia'   => $datos['horas_vigencia'],
                'fecha_asignacion' => $ahora,
                'vence_asignacion' => $ahora->copy()->addHours($datos['horas_vigencia']),
            ]);

            foreach ($datos['detalles'] as $linea) {
                $vehiculo = ($linea['vehiculo_id'] ?? null) ? Vehiculo::find($linea['vehiculo_id']) : null;
                $tipo = $linea['tipo_destino'] === 'vehiculo'
                    ? ($vehiculo?->tipo_combustible ?: 'diesel')
                    : 'gasolina';
                $estimacion = CombustibleEstimator::calcular(
                    (float) $linea['monto'],
                    CombustibleEstimator::clavePrecio($tipo),
                    $vehiculo
                ) ?? [];

                $orden->detalles()->create([
                    'tipo_destino'     => $linea['tipo_destino'],
                    'vehiculo_id'      => $linea['vehiculo_id'] ?? null,
                    'descripcion_mgp'  => $linea['descripcion_mgp'] ?? null,
                    'tipo_combustible' => $tipo,
                    'monto'            => $linea['monto'],
                ] + $this->estimacionPersistible($estimacion));
            }

            $solicitud->update(['estado' => 'aprobada']);

            return $orden;
        });

        // Se avisa por correo al conductor y a contabilidad (fuera de la transacción)
        $orden->load('detalles.evidencias', 'gasolinera', 'conductor.usuario');
        $orden->avisar(new OrdenAsignada($orden));

        return response()->json($orden, 201);
    }

    public function estimar(Request $request)
    {
        $datos = $request->validate([
            'monto'            => 'required|numeric|min:0.01',
            'vehiculo_id'      => 'nullable|required_without:tipo_combustible|exists:vehiculos,id',
            'tipo_combustible' => 'nullable|in:diesel,gasolina',
            'ciudad'           => 'nullable|in:tegucigalpa,san_pedro_sula',
            'combustible'      => 'nullable|in:regular,super,diesel',
        ]);

        $vehiculo = ($datos['vehiculo_id'] ?? null) ? Vehiculo::findOrFail($datos['vehiculo_id']) : null;
        $tipo = $vehiculo?->tipo_combustible ?: ($datos['tipo_combustible'] ?? 'gasolina');
        $clave = CombustibleEstimator::clavePrecio($tipo, $datos['combustible'] ?? null);
        $estimacion = CombustibleEstimator::calcular(
            (float) $datos['monto'],
            $clave,
            $vehiculo,
            $datos['ciudad'] ?? null
        );

        abort_unless($estimacion, 422, 'No hay un precio vigente para ese combustible y ciudad.');

        return response()->json([
            'monto_autorizado'       => round((float) $datos['monto'], 2),
            'combustible'            => $estimacion['combustible_precio'],
            'ciudad'                 => $estimacion['ciudad_precio'],
            'precio_por_galon'       => $estimacion['precio_galon'],
            'vigente_desde'          => $estimacion['precio_vigente_desde'],
            'galones_aproximados'    => $estimacion['galones_estimados'],
            'litros_aproximados'     => $estimacion['litros_estimados'],
            'rendimiento_km_litro'   => $estimacion['rendimiento_km_litro'],
            'kilometros_aproximados' => $estimacion['km_estimados'],
        ]);
    }

    private function estimacionPersistible(array $estimacion): array
    {
        $campos = [
            'combustible_precio',
            'ciudad_precio',
            'precio_galon',
            'precio_vigente_desde',
            'galones_estimados',
            'km_estimados',
        ];
        $columnasDisponibles = array_filter(
            $campos,
            fn (string $campo) => Schema::hasColumn('detalle_ordenes', $campo)
        );

        return array_intersect_key($estimacion, array_flip($columnasDisponibles));
    }

    // El administrador puede dar una nueva oportunidad a una orden vencida.
    public function rehabilitar(Request $request, string $id)
    {
        $datos = $request->validate([
            'horas_vigencia' => 'required|integer|between:1,3',
        ]);

        $orden = Orden::with(['detalles', 'gasolinera', 'conductor.usuario'])->findOrFail($id);

        abort_unless($orden->estado === 'vencida', 422, 'Solo se pueden rehabilitar órdenes vencidas.');

        $ahora = now();

        $orden->update([
            'estado'           => 'abierta_tarde',
            'horas_vigencia'   => $datos['horas_vigencia'],
            'justificacion'    => null,
            'asignado_por'     => $request->user()->id,
            'fecha_asignacion' => $ahora,
            'vence_asignacion' => $ahora->copy()->addHours($datos['horas_vigencia']),
            'fecha_apertura'   => $ahora,
            'vence_apertura'   => $ahora->copy()->addMinutes(15),
            'fecha_completada' => null,
        ]);

        $orden->avisar(new OrdenAsignada($orden));

        return response()->json($orden->fresh([
            'detalles.vehiculo',
            'gasolinera',
            'conductor.usuario',
        ]));
    }

    public function show(Request $request, string $id)
    {
        Orden::marcarVencidas();

        $orden = Orden::with(['conductor.usuario:id,name', 'gasolinera', 'detalles.vehiculo', 'detalles.evidencias'])->findOrFail($id);

        if ($conductor = $request->user()->conductor) {
            abort_if($orden->conductor_id !== $conductor->id, 403); // no es suya
            abort_if($orden->estado === 'vencida', 404);            // vencida: el conductor ya no la ve

            return $this->presentarParaConductor($orden);
        }

        return $orden;
    }

    // El conductor escanea el QR de la gasolinera: la orden pasa de pendiente a abierta
    public function escanear(Request $request)
    {
        $request->validate(['codigo_qr' => 'required|string']);
        $conductor = $request->user()->conductor ?? abort(403);

        Orden::marcarVencidas(); // una orden pendiente ya vencida no debe poder abrirse

        $gasolinera = Gasolinera::where('codigo_qr', $request->codigo_qr)->where('activo', true)->first();

        // Busca una orden pendiente de este conductor en esa gasolinera
        $orden = $gasolinera
            ? Orden::where('conductor_id', $conductor->id)
                ->where('gasolinera_id', $gasolinera->id)
                ->where('estado', 'pendiente')
                ->first()
            : null;

        if (! $orden) { // mismo mensaje en todos los casos, sin exponer datos
            return response()->json(['mensaje' => 'No tienes una orden pendiente para esta gasolinera.'], 404);
        }

        // Desde el escaneo tiene 15 minutos para subir las evidencias
        $orden->update([
            'estado'         => 'abierta',
            'fecha_apertura' => now(),
            'vence_apertura' => now()->addMinutes(15),
        ]);

        return $orden->load('detalles.vehiculo', 'gasolinera', 'detalles.evidencias');
    }

    // Sube una de las 4 evidencias de una línea. Al completar todas las líneas, la orden se cierra.
    public function subirEvidencia(Request $request, string $id)
    {
        $datos = $request->validate([
            'foto' => 'required|image|mimes:jpg,jpeg,png|max:5120', // máx. 5 MB
            'tipo' => 'required|in:' . implode(',', Evidencia::TIPOS), // bomba|tablero|tanque|factura
            'justificacion' => 'nullable|string|max:500',
        ]);

        $conductor = $request->user()->conductor ?? abort(403);
        $detalle = DetalleOrden::findOrFail($id);

        Orden::marcarVencidas();
        $orden = $detalle->orden()->first();

        abort_unless($orden->conductor_id === $conductor->id, 403);                // debe ser suya
        abort_unless(in_array($orden->estado, ['abierta', 'abierta_tarde'], true), 422, 'La orden no está abierta o ya venció.');

        if ($orden->estado === 'abierta_tarde' && ! $orden->justificacion && empty($datos['justificacion'])) {
            abort(422, 'La justificación es obligatoria para una orden abierta tarde.');
        }

        if (! empty($datos['justificacion'])) {
            $orden->update(['justificacion' => $datos['justificacion']]);
        }

        // Una sola foto de cada tipo por línea
        abort_if(
            $detalle->evidencias()->where('tipo', $datos['tipo'])->exists(),
            422,
            'Ese tipo de evidencia ya fue subido para esta línea.'
        );

        DB::transaction(function () use ($request, $detalle, $orden, $datos) {
            // storage/app/private/evidencias/{id_orden}/{id_detalle}/{tipo}/archivo
            $ruta = $request->file('foto')->store(
                "evidencias/{$orden->id}/{$detalle->id}/{$datos['tipo']}",
                'local'
            );

            $detalle->evidencias()->create([
                'tipo'         => $datos['tipo'],
                'ruta'         => $ruta,
                'fecha_subida' => now(),
            ]);

            // La orden se completa solo cuando TODAS las líneas tienen las 4 fotos
            $completa = $orden->detalles()->with('evidencias')->get()->every(
                fn (DetalleOrden $linea) => $linea->evidencias
                    ->pluck('tipo')
                    ->unique()
                    ->count() >= count(Evidencia::TIPOS)
            );

            if ($completa) {
                $orden->update([
                    'estado' => $orden->estado === 'abierta_tarde' ? 'completada_tarde' : 'completada',
                    'fecha_completada' => now(),
                ]);
            }
        });

        // Se recarga para ver el estado real tras la transacción
        $orden = $orden->fresh(['detalles.evidencias', 'gasolinera', 'conductor.usuario']);

        // Solo se avisa si esta foto fue la que cerró la orden
        if (in_array($orden->estado, ['completada', 'completada_tarde'], true)) {
            $orden->avisar(new OrdenCompletada($orden));
        }

        return $orden;
    }

    // Antes del escaneo la orden está oculta: el conductor no ve montos ni líneas
    private function presentarParaConductor(Orden $orden)
    {
        if ($orden->estado === 'pendiente') {
            return [
                'id'               => $orden->id,
                'numero_orden'     => $orden->numero_orden,
                'estado'           => $orden->estado,
                'gasolinera'       => $orden->gasolinera?->nombre,
                'vence_asignacion' => $orden->vence_asignacion,
            ];
        }

        return $orden;
    }
}
