<?php

namespace App\Modules\Sales\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A scheduled report, with the file attached (xlsx, csv or pdf). */
class ScheduledReportMail extends Mailable
{
    use Queueable;

    public function __construct(public string $title, public string $period, public ?string $company, public array $lines, public array $file) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "{$this->title} · {$this->period}");
    }

    public function content(): Content
    {
        return new Content(view: 'sales.report-mail');
    }

    public function attachments(): array
    {
        return [Attachment::fromData(fn () => file_get_contents($this->file['path']), $this->file['name'])->withMime($this->file['mime'])];
    }
}
