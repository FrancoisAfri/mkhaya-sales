<?php

namespace App\Modules\Sales\Services\Mail;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Microsoft Graph, app-only OAuth 2.0 (client credentials), reading ONE mailbox (SRS §5.1).
 *
 * Least privilege: the Entra app needs only the application permission Mail.Read, and the client's
 * Exchange admin should restrict it to the reporting mailbox with an Application Access Policy
 * (New-ApplicationAccessPolicy -AccessRight RestrictAccess …). Nothing is sent, moved or deleted.
 */
class GraphMailClient
{
    private const LOGIN = 'https://login.microsoftonline.com';
    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    public function __construct(
        private readonly string $tenantId,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $mailbox,
        private readonly string $folder = 'inbox',
    ) {}

    /** @return array{token: string, expires_at: int} */
    public function token(): array
    {
        $key = 'graph-token:' . sha1($this->tenantId . $this->clientId . $this->clientSecret);

        return Cache::remember($key, 3000, function () {
            $r = Http::asForm()->timeout(20)->retry(2, 1000, throw: false)
                ->post(self::LOGIN . "/{$this->tenantId}/oauth2/v2.0/token", [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ]);
            if (! $r->successful()) {
                throw new RuntimeException('Microsoft sign-in failed: ' . ($r->json('error_description') ?? $r->status()));
            }

            return ['token' => $r->json('access_token'), 'expires_at' => time() + (int) $r->json('expires_in', 3600)];
        });
    }

    /** @return array<int, array{message_id: ?string, subject: string, from_email: string, date: ?string, attachments: array}> */
    public function fetchSince(\DateTimeInterface $since, ?string $from = null, int $limit = 25): array
    {
        $filter = 'receivedDateTime ge ' . gmdate('Y-m-d\TH:i:s\Z', $since->getTimestamp()) . ' and hasAttachments eq true';
        if ($from && str_contains($from, '@')) {
            $filter .= " and from/emailAddress/address eq '" . str_replace("'", "''", $from) . "'";
        }
        $messages = $this->get("/users/{$this->mailbox}/mailFolders/{$this->folder}/messages", [
            '$filter' => $filter,
            '$select' => 'id,internetMessageId,subject,from,receivedDateTime',
            '$orderby' => 'receivedDateTime desc',
            '$top' => $limit,
        ])['value'] ?? [];

        return array_map(fn ($m) => [
            'id' => $m['id'],
            'message_id' => $m['internetMessageId'] ?? $m['id'],
            'subject' => (string) ($m['subject'] ?? ''),
            'from_email' => strtolower((string) ($m['from']['emailAddress']['address'] ?? '')),
            'date' => $m['receivedDateTime'] ?? null,
            'attachments' => fn () => $this->attachments($m['id']),
        ], $messages);
    }

    /** @return array<int, array{name: string, bytes: string}> */
    private function attachments(string $messageId): array
    {
        $items = $this->get("/users/{$this->mailbox}/messages/{$messageId}/attachments")['value'] ?? [];

        return collect($items)
            ->filter(fn ($a) => ($a['@odata.type'] ?? '') === '#microsoft.graph.fileAttachment')
            ->map(fn ($a) => ['name' => basename((string) $a['name']), 'bytes' => (string) base64_decode($a['contentBytes'] ?? '')])
            ->values()->all();
    }

    private function get(string $path, array $query = []): array
    {
        $r = Http::withToken($this->token()['token'])->timeout(60)->retry(3, 2000, throw: false)
            ->get(self::GRAPH . $path, $query);
        if (! $r->successful()) {
            throw new RuntimeException('Microsoft Graph refused the request: ' . ($r->json('error.message') ?? $r->status()));
        }

        return $r->json();
    }
}
