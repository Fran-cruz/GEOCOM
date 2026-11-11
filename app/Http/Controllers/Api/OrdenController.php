<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{DetalleOrden, Gasolinera, Orden, Solicitud};
use App\Notifications\{OrdenAsignada, OrdenCompletada};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrdenController extends Controller
{
    // Consulta con filtros (estado, conductor, gasolinera, fechas)
    public function index(Request $request)
    {
        Orden::marcarVencidas(); // respaldo: actualiza vencimientos antes de listar

        // when() aplica cada filtro solo si viene en la petición
        $consulta = Orden::with(['conductor.usuario:id,name', 'gasolinera', 'detalles.vehiculo'])
            ->when($request->estado,        fn ($q, $v) => $q->where('estado', $v))
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
                $orden->detalles()->create([
                    'tipo_destino'     => $linea['tipo_destino'],
                    'vehiculo_id'      => $linea['vehiculo_id'] ?? null,
                    'descripcion_mgp'  => $linea['descripcion_mgp'] ?? null,
                    // Regla del negocio: vehículos con diésel, MGP con gasolina
                    'tipo_combustible' => $linea['tipo_destino'] === 'vehiculo' ? 'diesel' : 'gasolina',
                    'monto'            => $linea['monto'],
                ]);
            }

            $solicitud->update(['estado' => 'aprobada']);

            return $orden;
        });

        // Se avisa por correo al conductor y a contabilidad (fuera de la transacción)
        $orden->load('detalles', 'gasolinera', 'conductor.usuario');
        $orden->avisar(new OrdenAsignada($orden));

        return response()->json($orden, 201);
    }

    public function show(Request $request, string $id)
    {
        Orden::marcarVencidas();

        $orden = Orden::with(['conductor.usuario:id,name', 'gasolinera', 'detalles.vehiculo'])->findOrFail($id);

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

        return $orden->load('detalles.vehiculo', 'gasolinera');
    }

    // Evidencia por línea; al completar todas, la orden se cierra ($id es el id del detalle)
    public function subirEvidencia(Request $request, string $id)
    {
        $request->validate(['foto' => 'required|image|mimes:jpg,jpeg,png|max:5120']); // máx. 5 MB

        $conductor = $request->user()->conductor ?? abort(403);
        $detalle = DetalleOrden::findOrFail($id);

        Orden::marcarVencidas();
        $orden = $detalle->orden()->first();

        abort_unless($orden->conductor_id === $conductor->id, 403);                // debe ser suya
        abort_unless($orden->estado === 'abierta', 422, 'La orden no está abierta o ya venció.');
        abort_if($detalle->ruta_evidencia, 422, 'Esta línea ya tiene evidencia.');  // una foto por línea

        DB::transaction(function () use ($request, $detalle, $orden) {
            // Se guarda en storage/app/public/evidencias/{id_orden}/...
            $ruta = $request->file('foto')->store("evidencias/{$orden->id}", 'public');
            $detalle->update(['ruta_evidencia' => $ruta, 'fecha_evidencia' => now()]);

            // Si ya no quedan líneas sin foto, la orden se completa
            if ($orden->detalles()->whereNull('ruta_evidencia')->doesntExist()) {
                $orden->update(['estado' => 'completada', 'fecha_completada' => now()]);
            }
        });

        // Se recarga para ver el estado real tras la transacción
        $orden = $orden->fresh(['detalles', 'gasolinera', 'conductor.usuario']);

        // Solo se avisa si esta foto fue la que cerró la orden
        if ($orden->estado === 'completada') {
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