<?php

namespace App\Modules\Sales\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SalesAlert extends Notification
{
    use Queueable;

    public function __construct(public string $title, public string $body, public string $url) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)->subject($this->title)->line($this->body)->action('Open', $this->url);
    }

    public function toArray($notifiable): array
    {
        return ['title' => $this->title, 'message' => $this->body, 'url' => $this->url, 'module' => 'sales'];
    }
}
