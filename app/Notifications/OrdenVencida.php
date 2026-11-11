<?php

namespace App\Notifications;

use App\Models\Orden;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrdenVencida extends Notification
{
    public function __construct(public Orden $orden) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Orden {$this->orden->numero_orden} vencida")
            ->greeting("Hola {$notifiable->name}")
            ->line("La orden {$this->orden->numero_orden} venció sin completarse.")
            ->line("Gasolinera: {$this->orden->gasolinera->nombre}")
            ->line('Si aún se necesita el combustible, hay que generar una nueva solicitud.');
    }
}