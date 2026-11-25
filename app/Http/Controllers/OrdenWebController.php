<?php

namespace App\Http\Controllers;

use App\Models\Evidencia;
use App\Models\Gasolinera;
use App\Models\Orden;
use App\Models\Solicitud;
use App\Models\Vehiculo;
use App\Notifications\OrdenAsignada;
use App\Http\Controllers\Concerns\ValidatesWebRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OrdenWebController extends Controller
{
    use ValidatesWebRequests;

    public function index(Request $request)
    {
        Orden::marcarVencidas();
        $filtros = $this->validateWeb($request, [
            'estado' => ['nullable', 'in:pendiente,abierta,completada,vencida,abierta_tarde,completada_tarde'],
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

        return Inertia::render('Ordenes/Index', [
            'ordenes' => Orden::with(['conductor.usuario:id,name', 'gasolinera', 'detalles.vehiculo', 'detalles.evidencias'])
                ->when($filtros['estado'] ?? null, fn ($query, $value) => $query->where('estado', $value))
                ->when($filtros['conductor_id'] ?? null, fn ($query, $value) => $query->where('conductor_id', $value))
                ->when($filtros['gasolinera_id'] ?? null, fn ($query, $value) => $query->where('gasolinera_id', $value))
                ->when($filtros['desde'] ?? null, fn ($query, $value) => $query->whereDate('fecha_asignacion', '>=', $value))
                ->when($filtros['hasta'] ?? null, fn ($query, $value) => $query->whereDate('fecha_asignacion', '<=', $value))
                ->latest('fecha_asignacion')
                ->paginate(15)
                ->withQueryString(),
            'filtros' => $filtros,
            'conductores' => \App\Models\Conductor::with('usuario:id,name')->where('activo', true)->orderBy('id')->get(),
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
                $orden->detalles()->create([
                    'tipo_destino' => $linea['tipo_destino'],
                    'vehiculo_id' => $linea['vehiculo_id'] ?? null,
                    'descripcion_mgp' => $linea['descripcion_mgp'] ?? null,
                    'tipo_combustible' => $linea['tipo_destino'] === 'vehiculo' ? 'diesel' : 'gasolina',
                    'monto' => $linea['monto'],
                ]);
            }

            $solicitud->update(['estado' => 'aprobada']);

            return $orden;
        });

        $orden->load('detalles.evidencias', 'gasolinera', 'conductor.usuario');
        $orden->avisar(new OrdenAsignada($orden));

        return back()->with('success', 'La orden fue creada y la solicitud aprobada.');
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

        return Inertia::render('Ordenes/Show', ['orden' => $orden]);
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
}
