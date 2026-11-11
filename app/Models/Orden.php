<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Notifications\OrdenVencida;
use Illuminate\Support\Facades\Notification;

class Orden extends Model
{
    protected $table = 'ordenes';
    protected $fillable = [
        'numero_orden', 'solicitud_id', 'conductor_id', 'gasolinera_id', 'asignado_por',
        'estado', 'horas_vigencia', 'fecha_asignacion', 'vence_asignacion',
        'fecha_apertura', 'vence_apertura', 'fecha_completada',
    ];
    // Los timestamp se convierten en objetos Carbon (permiten ->format(), ->addHours(), etc.)
    protected $casts = [
        'fecha_asignacion' => 'datetime',
        'vence_asignacion' => 'datetime',
        'fecha_apertura'   => 'datetime',
        'vence_apertura'   => 'datetime',
        'fecha_completada' => 'datetime',
    ];

    public function solicitud()  { return $this->belongsTo(Solicitud::class, 'solicitud_id'); }
    public function conductor()  { return $this->belongsTo(Conductor::class, 'conductor_id'); }
    public function gasolinera() { return $this->belongsTo(Gasolinera::class, 'gasolinera_id'); }
    public function asignador()  { return $this->belongsTo(User::class, 'asignado_por'); }
    public function detalles()   { return $this->hasMany(DetalleOrden::class, 'orden_id'); }

    // Destinatarios de todo aviso: el conductor de la orden + los usuarios activos de contabilidad
    public function destinatarios()
    {
        $contabilidad = User::whereHas('roles', fn ($q) => $q
            ->where('nombre', 'contabilidad')
            ->where('rol_usuario.activo', true))
            ->get();

        // unique por si un usuario cayera en ambos grupos
        return $contabilidad->push($this->conductor->usuario)->unique('id');
    }

    // Envía el aviso sin romper la petición: si el correo falla, solo se registra en el log
    public function avisar($notificacion): void
    {
        try {
            Notification::send($this->destinatarios(), $notificacion);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // Marca como vencidas las órdenes que superaron su vigencia y avisa por correo.
    // pendiente vence con 'vence_asignacion'; abierta (ya escaneada) vence con 'vence_apertura'.
    public static function marcarVencidas(): void
    {
        foreach ([['pendiente', 'vence_asignacion'], ['abierta', 'vence_apertura']] as [$estado, $campo]) {
            static::with('gasolinera', 'conductor.usuario')
                ->where('estado', $estado)
                ->where($campo, '<', now())
                ->get()
                ->each(function (Orden $orden) use ($estado, $campo) {
                    // Update condicionado: si otro proceso (el comando o una consulta) ya la cambió,
                    // $cambio es 0 y el correo no se manda dos veces.
                    $cambio = static::where('id', $orden->id)
                        ->where('estado', $estado)
                        ->where($campo, '<', now())
                        ->update(['estado' => 'vencida']);

                    if ($cambio) {
                        $orden->avisar(new OrdenVencida($orden));
                    }
                });
        }
    }
}
