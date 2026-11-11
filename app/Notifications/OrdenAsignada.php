<?php

namespace App\Notifications;

use App\Models\Orden;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrdenAsignada extends Notification
{
    // Recibe la orden ya cargada con sus relaciones (gasolinera, conductor)
    public function __construct(public Orden $orden) {}

    // Canal de envío: solo correo
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    // $notifiable es el User que recibe el correo (conductor o contabilidad)
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Orden {$this->orden->numero_orden} asignada")
            ->greeting("Hola {$notifiable->name}")
            ->line("Se asignó la orden {$this->orden->numero_orden}.")
            ->line("Gasolinera: {$this->orden->gasolinera->nombre}")
            ->line("Vigencia: {$this->orden->horas_vigencia} hora(s), hasta el "
                . $this->orden->vence_asignacion->format('d/m/Y h:i A'))
            ->line('Escanea el QR de la gasolinera antes de que venza.');
    }
}