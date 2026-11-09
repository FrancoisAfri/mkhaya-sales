<?php

namespace App\Modules\Sales\Services\Mail;

use App\Modules\Sales\Models\Mailbox;

/** Builds the client for a mailbox. Bound in the container so tests can supply a fake inbox. */
class MailConnector
{
    /** @return object{fetchSince: callable} an object with fetchSince(DateTimeInterface, ?string): array */
    public function for(Mailbox $mailbox): object
    {
        $c = $mailbox->credentials ?? [];

        return match ($mailbox->provider) {
            'graph' => new GraphMailClient(
                (string) ($c['tenant_id'] ?? ''), (string) ($c['client_id'] ?? ''), (string) ($c['client_secret'] ?? ''),
                (string) $mailbox->mailbox_address, $mailbox->folder ?: 'inbox',
            ),
            'imap' => tap(new ImapClient(
                (string) ($c['host'] ?? ''), (int) ($c['port'] ?? 993), (string) ($c['encryption'] ?? 'ssl'),
                (string) ($mailbox->mailbox_address ?? ''), (string) ($c['password'] ?? ''), $mailbox->folder ?: 'INBOX',
            ), fn ($client) => $client->connect()),
            // Method 2: a secure central repository instead of email.
            'sftp' => tap(new SftpRepositoryClient(
                (string) ($c['host'] ?? ''), (int) ($c['port'] ?? 22), (string) ($mailbox->mailbox_address ?? ''),
                (string) ($c['password'] ?? ''), (string) ($c['private_key'] ?? ''), $mailbox->folder ?: '/', (string) ($c['host_fingerprint'] ?? ''),
            ), fn ($client) => $client->connect()),
            'sharepoint' => new SharePointRepositoryClient(
                new GraphMailClient((string) ($c['tenant_id'] ?? ''), (string) ($c['client_id'] ?? ''), (string) ($c['client_secret'] ?? ''), ''),
                (string) $mailbox->mailbox_address, (string) ($mailbox->folder ?? ''), (string) ($c['drive_id'] ?? ''),
            ),
            default => throw new \RuntimeException("Unknown source type {$mailbox->provider}."),
        };
    }
}
