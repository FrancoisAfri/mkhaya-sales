<?php

namespace App\Modules\Sales\Services\Mail;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Method 2, Microsoft variant: the report is collected from a SharePoint / OneDrive document library
 * folder through Microsoft Graph (app-only). The Entra app needs Sites.Selected (granted on this one
 * site) or Files.Read.All. Each file is one "message" with one attachment, as for SFTP. Read-only.
 */
class SharePointRepositoryClient
{
    private const GRAPH = 'https://graph.microsoft.com/v1.0';

    private ?string $driveId = null;

    /** @param string $site e.g. "contoso.sharepoint.com/sites/Sales", or blank when a drive id is given */
    public function __construct(
        private readonly GraphMailClient $auth,
        private readonly string $site,
        private readonly string $folder = '',
        private readonly string $drive = '',
    ) {}

    public function fetchSince(\DateTimeInterface $since, ?string $from = null, int $limit = 50): array
    {
        $drive = $this->drive();
        $folder = trim($this->folder, '/');
        $path = $folder === '' ? "/drives/{$drive}/root/children" : "/drives/{$drive}/root:/" . implode('/', array_map('rawurlencode', explode('/', $folder))) . ':/children';
        $items = $this->get($path, ['$top' => 200, '$select' => 'id,name,size,lastModifiedDateTime,file'])['value'] ?? [];

        return collect($items)
            ->filter(fn ($i) => isset($i['file']) && strtotime($i['lastModifiedDateTime'] ?? '') >= $since->getTimestamp())
            ->sortByDesc('lastModifiedDateTime')->take($limit)
            ->map(fn ($i) => [
                'id' => $i['id'],
                'message_id' => 'sharepoint:' . sha1($i['id'] . '|' . $i['lastModifiedDateTime'] . '|' . ($i['size'] ?? 0)),
                'subject' => (string) $i['name'],
                'from_email' => '',
                'date' => $i['lastModifiedDateTime'],
                'attachments' => fn () => [['name' => basename((string) $i['name']), 'bytes' => $this->download($drive, $i['id'])]],
            ])->values()->all();
    }

    private function drive(): string
    {
        if ($this->driveId) {
            return $this->driveId;
        }
        if ($this->drive !== '') {
            return $this->driveId = $this->drive;
        }
        // "host/sites/Name" → /sites/host:/sites/Name → the site's default document library.
        $site = preg_replace('#^https?://#', '', trim($this->site, '/'));
        [$host, $rest] = array_pad(explode('/', $site, 2), 2, '');
        if ($host === '') {
            throw new RuntimeException('Enter the SharePoint site, e.g. contoso.sharepoint.com/sites/Sales.');
        }
        $siteId = $this->get('/sites/' . $host . ($rest !== '' ? ':/' . $rest : ''))['id'] ?? null;

        return $this->driveId = (string) ($this->get("/sites/{$siteId}/drive")['id'] ?? throw new RuntimeException('The site has no document library.'));
    }

    private function download(string $drive, string $itemId): string
    {
        $r = Http::withToken($this->auth->token()['token'])->timeout(120)->retry(3, 2000, throw: false)->get(self::GRAPH . "/drives/{$drive}/items/{$itemId}/content");
        if (! $r->successful()) {
            throw new RuntimeException('Could not download the file from SharePoint (' . $r->status() . ').');
        }

        return $r->body();
    }

    private function get(string $path, array $query = []): array
    {
        $r = Http::withToken($this->auth->token()['token'])->timeout(60)->retry(3, 2000, throw: false)->get(self::GRAPH . $path, $query);
        if (! $r->successful()) {
            throw new RuntimeException('Microsoft Graph refused the request: ' . ($r->json('error.message') ?? $r->status()));
        }

        return $r->json();
    }
}
