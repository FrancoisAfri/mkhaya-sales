<?php

namespace App\Modules\Sales\Services\Mail;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use RuntimeException;

/**
 * Method 2 (meeting 2026-11-09): the report is collected from a secure central repository over SFTP
 * instead of email. Each file in the folder is presented as one "message" with one attachment, so
 * the mailbox pipeline (schedule, file rules, layout, duplicate check, auto-commit, alerts) applies
 * unchanged. Read-only: nothing is moved or deleted on the server.
 */
class SftpRepositoryClient
{
    private ?SFTP $sftp = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
        private readonly string $privateKey,
        private readonly string $folder = '/',
        private readonly string $hostFingerprint = '',
    ) {}

    public function connect(): void
    {
        if ($this->host === '' || $this->username === '') {
            throw new RuntimeException('Enter the SFTP server and user name.');
        }
        $sftp = new SFTP($this->host, $this->port ?: 22, 20);
        // Optional pinning: refuse to log in if the server's key is not the one agreed.
        if ($this->hostFingerprint !== '') {
            $key = $sftp->getServerPublicHostKey();
            $actual = $key ? 'SHA256:' . rtrim(base64_encode(hash('sha256', base64_decode(explode(' ', $key)[1] ?? ''), true)), '=') : '';
            if (! hash_equals(trim($this->hostFingerprint), $actual)) {
                throw new RuntimeException("The SFTP server's key ({$actual}) does not match the agreed fingerprint.");
            }
        }
        $secret = $this->privateKey !== '' ? PublicKeyLoader::load($this->privateKey, $this->password ?: false) : $this->password;
        if (! $sftp->login($this->username, $secret)) {
            throw new RuntimeException('SFTP login failed for ' . $this->username . '@' . $this->host . '.');
        }
        $this->sftp = $sftp;
    }

    /** @return array<int, array{id: string, message_id: string, subject: string, from_email: string, date: ?string, attachments: callable}> */
    public function fetchSince(\DateTimeInterface $since, ?string $from = null, int $limit = 50): array
    {
        $this->sftp ?? $this->connect();
        $dir = rtrim($this->folder ?: '/', '/') ?: '/';
        $list = $this->sftp->rawlist($dir);
        if ($list === false) {
            throw new RuntimeException("Folder {$dir} cannot be read on the SFTP server.");
        }
        $files = collect($list)
            ->filter(fn ($f, $name) => ($f['type'] ?? null) === 1 && ! str_starts_with((string) $name, '.') && (int) ($f['mtime'] ?? 0) >= $since->getTimestamp())
            ->sortByDesc(fn ($f) => $f['mtime'] ?? 0)->take($limit);

        return $files->map(fn ($f, $name) => [
            'id' => "{$dir}/{$name}",
            // Same name, same time and size = the same file; a re-upload gets a new id.
            'message_id' => 'sftp:' . sha1("{$dir}/{$name}|{$f['mtime']}|{$f['size']}"),
            'subject' => (string) $name,
            'from_email' => '',
            'date' => gmdate('c', (int) $f['mtime']),
            'attachments' => fn () => [['name' => (string) $name, 'bytes' => $this->download("{$dir}/{$name}")]],
        ])->values()->all();
    }

    private function download(string $path): string
    {
        $bytes = $this->sftp->get($path);
        if ($bytes === false) {
            throw new RuntimeException("Could not download {$path}.");
        }

        return $bytes;
    }
}
