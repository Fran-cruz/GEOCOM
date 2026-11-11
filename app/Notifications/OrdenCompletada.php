<?php

namespace App\Notifications;

use App\Models\Orden;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrdenCompletada extends Notification
{
    public function __construct(public Orden $orden) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Suma de todas las líneas de la orden (vehículo y/o MGP)
        $total = number_format($this->orden->detalles->sum('monto'), 2);

        return (new MailMessage)
            ->subject("Orden {$this->orden->numero_orden} completada")
            ->greeting("Hola {$notifiable->name}")
            ->line("La orden {$this->orden->numero_orden} se completó con todas sus evidencias.")
            ->line("Gasolinera: {$this->orden->gasolinera->nombre}")
            ->line("Total: L {$total}");
    }
}