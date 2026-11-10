<?php

namespace App\Modules\Sales\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SalesAlert extends Notification
{
    use Queueable;

    /** @param array<int, string> $channels in_app and/or email (a person's own alert chooses; system alerts use both) */
    public function __construct(public string $title, public string $body, public string $url, public array $channels = ['in_app', 'email']) {}

    public function via($notifiable): array
    {
        return array_values(array_filter([in_array('in_app', $this->channels, true) ? 'database' : null, in_array('email', $this->channels, true) ? 'mail' : null])) ?: ['database'];
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
