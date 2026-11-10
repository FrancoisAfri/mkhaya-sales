<?php

namespace App\Modules\Sales\Services\Mail;

use PDO;
use RuntimeException;

/**
 * Live connector (gap 6): read sales straight from the client's database — SQL Server, MySQL,
 * PostgreSQL or any ODBC source (e.g. SAP HANA) — with one saved SELECT. The result becomes a CSV
 * "file" for the normal import pipeline, so layouts, validation, duplicate protection and alerts
 * all apply. Read-only by design: only a single SELECT/WITH statement is accepted, the session is
 * set read-only where the database allows it, and there is a time limit.
 */
class DatabaseSourceClient
{
    public function __construct(
        private readonly string $driver,     // sqlsrv | mysql | pgsql | odbc
        private readonly string $host,
        private readonly int $port,
        private readonly string $database,
        private readonly string $username,
        private readonly string $password,
        private readonly string $query,
        private readonly string $odbcDsn = '',
    ) {}

    public static function assertSafe(string $sql): void
    {
        $s = trim(preg_replace('/--[^\n]*|\/\*.*?\*\//s', ' ', $sql));
        if (! preg_match('/^(select|with)\b/i', $s) || str_contains(rtrim($s, "; \n\t"), ';')
            || preg_match('/\b(insert|update|delete|merge|drop|alter|create|truncate|grant|revoke|exec|execute|call|into)\b/i', $s)) {
            throw new RuntimeException('Only one read-only SELECT statement is allowed.');
        }
    }

    public function fetchSince(\DateTimeInterface $since, ?string $from = null, int $limit = 1): array
    {
        self::assertSafe($this->query);
        $pdo = $this->connect();
        $sql = rtrim(trim($this->query), ';');
        $params = [];
        foreach (['from' => $since->format('Y-m-d'), 'to' => now()->format('Y-m-d')] as $k => $v) {
            if (str_contains($sql, ":{$k}")) {
                $params[$k] = $v;
            }
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $out = fopen('php://temp', 'w+');
        $rows = 0;
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($rows === 0) {
                fputcsv($out, array_keys($row));
            }
            fputcsv($out, array_map(fn ($v) => is_string($v) ? $v : (is_null($v) ? '' : (string) $v), array_values($row)));
            if (++$rows >= 500000) {
                break;
            }
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        if ($rows === 0) {
            return [];
        }
        $name = 'database-' . now()->format('Y-m-d-His') . '.csv';

        // The content decides the id: the same rows fetched twice are one "file".
        return [['id' => $name, 'message_id' => 'database:' . sha1($csv), 'subject' => $name, 'from_email' => '', 'date' => now()->toIso8601String(),
            'attachments' => [['name' => $name, 'bytes' => $csv]]]];
    }

    private function connect(): PDO
    {
        $dsn = match ($this->driver) {
            'sqlsrv' => "dblib:host={$this->host}:" . ($this->port ?: 1433) . ";dbname={$this->database}",
            'mysql' => "mysql:host={$this->host};port=" . ($this->port ?: 3306) . ";dbname={$this->database};charset=utf8mb4",
            'pgsql' => "pgsql:host={$this->host};port=" . ($this->port ?: 5432) . ";dbname={$this->database}",
            'odbc' => 'odbc:' . $this->odbcDsn,
            default => throw new RuntimeException('Choose SQL Server, MySQL, PostgreSQL or ODBC.'),
        };
        if ($this->driver !== 'odbc' && $this->host === '') {
            throw new RuntimeException('Enter the database server.');
        }
        try {
            $pdo = new PDO($dsn, $this->username, $this->password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 20]);
        } catch (\PDOException $e) {
            throw new RuntimeException('Could not connect to the database: ' . $e->getMessage());
        }
        // Read-only session and a 2-minute statement limit, where the database supports it.
        foreach (match ($this->driver) {
            'pgsql' => ['SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY', "SET statement_timeout = '120s'"],
            'mysql' => ['SET SESSION TRANSACTION READ ONLY', 'SET SESSION MAX_EXECUTION_TIME = 120000'],
            default => [],
        } as $stmt) {
            try {
                $pdo->exec($stmt);
            } catch (\Throwable) {
            }
        }

        return $pdo;
    }
}
