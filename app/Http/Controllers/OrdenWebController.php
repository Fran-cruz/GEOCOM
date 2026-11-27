<?php

namespace App\Http\Controllers;

use App\Models\Evidencia;
use App\Models\Gasolinera;
use App\Models\Orden;
use App\Models\Solicitud;
use App\Models\Vehiculo;
use App\Notifications\OrdenAsignada;
use App\Http\Controllers\Concerns\ValidatesWebRequests;
use App\Services\CombustibleEstimator;
use App\Models\Conductor;
use App\Models\DetalleOrden;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrdenWebController extends Controller
{
    use ValidatesWebRequests;

    private const ESTADOS_ORDEN = [
        'pendiente',
        'abierta',
        'abierta_tarde',
        'completada',
        'completada_tarde',
        'vencida',
        'cancelada',
    ];

    public function index(Request $request)
    {
        Orden::marcarVencidas();
        $filtros = $this->validateWeb($request, [
            'estado' => ['nullable', 'in:'.implode(',', self::ESTADOS_ORDEN)],
            'conductor_id' => ['nullable', 'exists:conductores,id'],
            'gasolinera_id' => ['nullable', 'exists:gasolineras,id'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ], [
            'estado.in' => 'El estado seleccionado no es válido.',
            'conductor_id.exists' => 'El conductor seleccionado no existe.',
            'gasolinera_id.exists' => 'La gasolinera seleccionada no existe.',
            'desde.date' => 'La fecha inicial no es válida.',
            'hasta.date' => 'La fecha final no es válida.',
            'hasta.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.',
        ]);

        $ordenes = Orden::with(['conductor.usuario:id,name', 'gasolinera', 'detalles.vehiculo', 'detalles.evidencias'])
                ->when($filtros['estado'] ?? null, fn ($query, $value) => $query->where('estado', $value))
                ->when($filtros['conductor_id'] ?? null, fn ($query, $value) => $query->where('conductor_id', $value))
                ->when($filtros['gasolinera_id'] ?? null, fn ($query, $value) => $query->where('gasolinera_id', $value))
                ->when($filtros['desde'] ?? null, fn ($query, $value) => $query->whereDate('fecha_asignacion', '>=', $value))
                ->when($filtros['hasta'] ?? null, fn ($query, $value) => $query->whereDate('fecha_asignacion', '<=', $value))
                ->latest('fecha_asignacion')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Orden $orden) => $this->serializarOrden($orden));

        return Inertia::render('Ordenes/Index', [
            'ordenes' => $ordenes,
            'filtros' => $filtros,
            'conductores' => Conductor::with('usuario:id,name')->where('activo', true)->orderBy('id')->get(),
            'gasolineras' => Gasolinera::where('activo', true)->orderBy('nombre')->get(),
            'vehiculos' => Vehiculo::where('activo', true)->orderBy('placa')->get(),
            'solicitudesPendientes' => Solicitud::with(['conductor.usuario:id,name', 'vehiculo'])
                ->where('estado', 'pendiente')
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $this->validateWeb($request, [
            'solicitud_id' => ['required', 'exists:solicitudes,id'],
            'gasolinera_id' => ['required', 'exists:gasolineras,id'],
            'horas_vigencia' => ['required', 'integer', 'between:1,3'],
            'detalles' => ['required', 'array', 'min:1', 'max:2'],
            'detalles.*.tipo_destino' => ['required', 'in:vehiculo,mgp'],
            'detalles.*.vehiculo_id' => ['nullable', 'required_if:detalles.*.tipo_destino,vehiculo', 'exists:vehiculos,id'],
            'detalles.*.descripcion_mgp' => ['nullable', 'required_if:detalles.*.tipo_destino,mgp', 'string', 'max:150'],
            'detalles.*.monto' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
        ], [
            'solicitud_id.required' => 'Debes seleccionar una solicitud.',
            'solicitud_id.exists' => 'La solicitud seleccionada no existe.',
            'gasolinera_id.required' => 'Debes seleccionar una gasolinera.',
            'gasolinera_id.exists' => 'La gasolinera seleccionada no existe.',
            'horas_vigencia.between' => 'La vigencia debe ser de 1 a 3 horas.',
            'detalles.min' => 'Agrega al menos un detalle.',
            'detalles.max' => 'Solo se permiten dos detalles.',
            'detalles.*.tipo_destino.in' => 'El destino debe ser vehículo o MGP.',
            'detalles.*.vehiculo_id.exists' => 'El vehículo seleccionado no existe.',
            'detalles.*.descripcion_mgp.required_if' => 'La descripción del MGP es obligatoria.',
            'detalles.*.monto.required' => 'El monto de cada detalle es obligatorio.',
            'detalles.*.monto.min' => 'El monto debe ser mayor que cero.',
        ]);

        $destinos = array_column($datos['detalles'], 'tipo_destino');
        if (count($destinos) !== count(array_unique($destinos))) {
            throw ValidationException::withMessages([
                'detalles' => 'Solo se permite un detalle por cada tipo de destino.',
            ]);
        }

        $orden = DB::transaction(function () use ($datos, $request) {
            $solicitud = Solicitud::whereKey($datos['solicitud_id'])->lockForUpdate()->firstOrFail();
            abort_unless($solicitud->estado === 'pendiente', 422, 'La solicitud ya fue procesada.');
            abort_if(Orden::where('solicitud_id', $solicitud->id)->exists(), 422, 'La solicitud ya tiene una orden asignada.');

            $ahora = now();
            $orden = Orden::create([
                'numero_orden' => 'ORD-'.$ahora->format('Ymd').'-'.strtoupper(Str::random(5)),
                'solicitud_id' => $solicitud->id,
                'conductor_id' => $solicitud->conductor_id,
                'gasolinera_id' => $datos['gasolinera_id'],
                'asignado_por' => $request->user()->id,
                'estado' => 'pendiente',
                'horas_vigencia' => $datos['horas_vigencia'],
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
                    'tipo_destino' => $linea['tipo_destino'],
                    'vehiculo_id' => $linea['vehiculo_id'] ?? null,
                    'descripcion_mgp' => $linea['descripcion_mgp'] ?? null,
                    'tipo_combustible' => $tipo,
                    'monto' => $linea['monto'],
                ] + $this->estimacionPersistible($estimacion));
            }

            $solicitud->update(['estado' => 'aprobada']);

            return $orden;
        });

        $orden->load('detalles.evidencias', 'gasolinera', 'conductor.usuario');
        $orden->avisar(new OrdenAsignada($orden));

        return back()->with('success', 'La orden fue creada y la solicitud aprobada.');
    }

    public function estimar(Request $request)
    {
        $datos = $this->validateWeb($request, [
            'monto' => ['required', 'numeric', 'min:0.01'],
            'vehiculo_id' => ['nullable', 'required_without:tipo_combustible', 'exists:vehiculos,id'],
            'tipo_combustible' => ['nullable', 'in:diesel,gasolina'],
            'ciudad' => ['nullable', 'in:tegucigalpa,san_pedro_sula'],
            'combustible' => ['nullable', 'in:regular,super,diesel'],
        ], [
            'monto.required' => 'El monto es obligatorio.',
            'monto.numeric' => 'El monto debe ser numérico.',
            'monto.min' => 'El monto debe ser mayor que cero.',
            'vehiculo_id.exists' => 'El vehículo seleccionado no existe.',
            'tipo_combustible.in' => 'El combustible seleccionado no es válido.',
            'ciudad.in' => 'La ciudad seleccionada no es válida.',
            'combustible.in' => 'La variante de combustible seleccionada no es válida.',
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
            'monto_autorizado' => round((float) $datos['monto'], 2),
            'combustible' => $estimacion['combustible_precio'],
            'ciudad' => $estimacion['ciudad_precio'],
            'precio_por_galon' => $estimacion['precio_galon'],
            'vigente_desde' => $estimacion['precio_vigente_desde'],
            'galones_aproximados' => $estimacion['galones_estimados'],
            'litros_aproximados' => $estimacion['litros_estimados'],
            'rendimiento_km_litro' => $estimacion['rendimiento_km_litro'],
            'kilometros_aproximados' => $estimacion['km_estimados'],
        ]);
    }

    public function rehabilitar(Request $request, string $id)
    {
        $datos = $this->validateWeb($request, [
            'horas_vigencia' => ['required', 'integer', 'between:1,3'],
            'password' => ['required', 'string'],
        ], [
            'horas_vigencia.required' => 'La vigencia es obligatoria.',
            'horas_vigencia.between' => 'La vigencia debe ser de 1 a 3 horas.',
            'password.required' => 'La contraseña del administrador es obligatoria.',
        ]);

        if (! Hash::check($datos['password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => 'La contraseña del administrador es incorrecta.',
            ]);
        }

        $orden = DB::transaction(function () use ($datos, $id, $request) {
            $orden = Orden::whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($orden->estado === 'vencida', 422, 'Solo se pueden rehabilitar órdenes vencidas.');

            $ahora = now();
            $orden->update([
                'estado' => 'abierta_tarde',
                'horas_vigencia' => $datos['horas_vigencia'],
                'justificacion' => null,
                'asignado_por' => $request->user()->id,
                'fecha_asignacion' => $ahora,
                'vence_asignacion' => $ahora->copy()->addHours($datos['horas_vigencia']),
                'fecha_apertura' => $ahora,
                'vence_apertura' => $ahora->copy()->addMinutes(15),
                'fecha_completada' => null,
            ]);

            return $orden->fresh(['detalles.vehiculo', 'gasolinera', 'conductor.usuario']);
        });

        $orden->avisar(new OrdenAsignada($orden));

        return back()->with('success', 'La orden fue rehabilitada correctamente.');
    }

    public function show(string $id)
    {
        Orden::marcarVencidas();
        $orden = Orden::with([
            'solicitud',
            'conductor.usuario:id,name,email',
            'gasolinera',
            'detalles.vehiculo',
            'detalles.evidencias',
        ])->findOrFail($id);

        return Inertia::render('Ordenes/Show', ['orden' => $this->serializarOrden($orden)]);
    }

    public function verEvidencia(string $id)
    {
        $evidencia = Evidencia::findOrFail($id);
        abort_unless(Storage::disk('local')->exists($evidencia->ruta), 404);

        return Storage::disk('local')->response($evidencia->ruta, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function serializarOrden(Orden $orden): array
    {
        $datos = $orden->toArray();

        foreach ($datos['detalles'] as $indice => &$detalle) {
            $modeloDetalle = $orden->detalles[$indice] ?? null;
            $tipoCombustible = $modeloDetalle?->combustible_precio
                ?: CombustibleEstimator::clavePrecio($modeloDetalle?->tipo_combustible ?? 'gasolina');
            $ciudad = CombustibleEstimator::ciudad($modeloDetalle?->ciudad_precio);
            $estimacion = $modeloDetalle
                ? CombustibleEstimator::calcular(
                    (float) $modeloDetalle->monto,
                    $tipoCombustible,
                    $modeloDetalle->vehiculo,
                    $ciudad,
                    $orden->fecha_asignacion
                )
                : null;
            $galones = $modeloDetalle?->galones_estimados ?? $estimacion['galones_estimados'] ?? null;
            $detalle['combustible_precio'] = $modeloDetalle?->combustible_precio
                ?? $estimacion['combustible_precio']
                ?? $tipoCombustible;
            $detalle['ciudad_precio'] = $modeloDetalle?->ciudad_precio
                ?? $estimacion['ciudad_precio']
                ?? $ciudad;
            $detalle['precio_galon'] = $modeloDetalle?->precio_galon
                ?? $estimacion['precio_galon']
                ?? null;
            $detalle['precio_vigente_desde'] = $modeloDetalle?->precio_vigente_desde?->toDateString()
                ?? $estimacion['precio_vigente_desde']
                ?? null;
            $detalle['galones_estimados'] = $galones;
            $detalle['litros_estimados'] = $estimacion['litros_estimados']
                ?? ($galones === null ? null : round((float) $galones * CombustibleEstimator::LITROS_POR_GALON, 2));
            $detalle['km_estimados'] = $modeloDetalle?->km_estimados
                ?? $estimacion['km_estimados']
                ?? null;

            foreach ($detalle['evidencias'] as &$evidencia) {
                $evidencia['url'] = route('web.evidencias.ver', ['id' => $evidencia['id']]);
            }
            unset($evidencia);
        }
        unset($detalle);

        return $datos;
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
}
