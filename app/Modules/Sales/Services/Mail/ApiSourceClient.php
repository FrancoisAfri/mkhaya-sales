<?php

namespace App\Modules\Sales\Services\Mail;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Live connector (gap 6): read sales from a web API — JSON (a list of rows, optionally under a
 * key such as "data.items") or CSV — over HTTPS with a bearer token or API key. `{from}` and `{to}`
 * in the address become the look-back dates. The rows become a CSV "file" for the import pipeline.
 * Internal network addresses are refused.
 */
class ApiSourceClient
{
    public function __construct(
        private readonly string $url,
        private readonly string $authType,   // none | bearer | header
        private readonly string $secret,
        private readonly string $headerName = 'X-API-Key',
        private readonly string $rowsPath = '',
    ) {}

    public static function assertSafeUrl(string $url): void
    {
        $p = parse_url($url);
        if (($p['scheme'] ?? '') !== 'https' || empty($p['host'])) {
            throw new RuntimeException('The API address must start with https://.');
        }
        $ips = gethostbynamel($p['host']) ?: [];
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('Internal network addresses are not allowed.');
            }
        }
    }

    public function fetchSince(\DateTimeInterface $since, ?string $from = null, int $limit = 1): array
    {
        $url = str_replace(['{from}', '{to}'], [$since->format('Y-m-d'), now()->format('Y-m-d')], $this->url);
        self::assertSafeUrl($url);
        $req = Http::timeout(120)->retry(2, 2000, throw: false)->withOptions(['allow_redirects' => false]);
        $req = match ($this->authType) {
            'bearer' => $req->withToken($this->secret),
            'header' => $req->withHeaders([$this->headerName ?: 'X-API-Key' => $this->secret]),
            default => $req,
        };
        $r = $req->get($url);
        if (! $r->successful()) {
            throw new RuntimeException('The API answered ' . $r->status() . '.');
        }
        $csv = str_contains((string) $r->header('Content-Type'), 'csv') ? $r->body() : $this->jsonToCsv($r->json());
        if (trim($csv) === '') {
            return [];
        }
        $name = 'api-' . now()->format('Y-m-d-His') . '.csv';

        return [['id' => $name, 'message_id' => 'api:' . sha1($csv), 'subject' => $name, 'from_email' => '', 'date' => now()->toIso8601String(),
            'attachments' => [['name' => $name, 'bytes' => $csv]]]];
    }

    private function jsonToCsv($json): string
    {
        $rows = $this->rowsPath !== '' ? data_get($json, $this->rowsPath) : $json;
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new RuntimeException('The API did not return a list of rows' . ($this->rowsPath ? " at \"{$this->rowsPath}\"" : '') . '.');
        }
        if (! $rows) {
            return '';
        }
        $cols = array_keys(array_merge(...array_map(fn ($r) => (array) $r, array_slice($rows, 0, 50))));
        $out = fopen('php://temp', 'w+');
        fputcsv($out, $cols);
        foreach ($rows as $r) {
            $r = (array) $r;
            fputcsv($out, array_map(fn ($c) => is_scalar($r[$c] ?? null) ? (string) $r[$c] : '', $cols));
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }
}
