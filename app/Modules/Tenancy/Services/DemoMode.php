<?php

namespace App\Modules\Tenancy\Services;

use Illuminate\Support\Facades\DB;

/**
 * Keeps a demo company from reaching the outside world. A reseller clicks through the real system
 * in front of a prospect, so the system really does try to email, text, publish and pay. Two
 * independent checks, because either alone has a gap: the company in scope catches anything typed
 * live (a prospect's own address), and the reserved addresses catch a scheduled job with no company
 * in scope. Queued jobs carry their company on the payload, so a worker is covered by the first.
 */
class DemoMode
{
    /** Reserved by RFC 2606: nothing can ever be delivered to it. */
    public const EMAIL_DOMAIN = 'demo.mkhaya.invalid';

    /** Hosts a demo may still POST to: the AI models the screens are built on. Nothing else. */
    public const AI_HOSTS = ['api.anthropic.com', 'api.openai.com', 'generativelanguage.googleapis.com', 'api.groq.com', 'openrouter.ai',
        'api.cloudflare.com', 'api.together.xyz', 'api-inference.huggingface.co', 'router.huggingface.co', 'api.mistral.ai', 'api.deepseek.com', 'api.cerebras.ai'];

    private const NUMBER_PREFIX = '/^(?:\+?27|0)105550/';

    /** @var array<int, bool> */
    private array $known = [];

    public function __construct(private readonly CurrentCompany $current) {}

    public function active(): bool
    {
        return $this->isDemoCompany($this->current->id());
    }

    public function isDemoCompany(?int $companyId): bool
    {
        if (! $companyId) {
            return false;
        }

        return $this->known[$companyId] ??= DB::table('companies')->where('id', $companyId)->whereNotNull('demo_reseller_id')->exists();
    }

    /** A person's address on the reserved domain, or on one of its per-reseller subdomains. */
    public function isDemoEmail(?string $email): bool
    {
        $domain = strtolower((string) substr(strrchr(trim((string) $email), '@') ?: '', 1));

        return $domain === self::EMAIL_DOMAIN || str_ends_with($domain, '.' . self::EMAIL_DOMAIN);
    }

    public function isDemoNumber(?string $number): bool
    {
        return (bool) preg_match(self::NUMBER_PREFIX, (string) preg_replace('/[\s\-()]/', '', (string) $number));
    }

    public function blocksEmail(array $addresses): bool
    {
        return $this->active() || ($addresses !== [] && collect($addresses)->every(fn ($a) => $this->isDemoEmail($a)));
    }

    public function blocksNumbers(string|array $numbers, ?int $companyId = null): bool
    {
        if ($this->active() || $this->isDemoCompany($companyId)) {
            return true;
        }
        $numbers = (array) $numbers;

        return $numbers !== [] && collect($numbers)->every(fn ($n) => $this->isDemoNumber($n));
    }

    /** Reading the web is harmless; only a write (publish, pay, send) to anything but an AI model is stopped. */
    public function blocksRequest(string $method, string $host): bool
    {
        return $this->active() && ! in_array(strtoupper($method), ['GET', 'HEAD'], true) && ! in_array(strtolower($host), self::AI_HOSTS, true);
    }

    public function forget(int $companyId): void
    {
        unset($this->known[$companyId]);
    }
}
