<?php

namespace App\Core\AI\Services;

use App\Core\AI\Models\AiChatMessage;
use App\Core\AI\Models\AiChatSession;
use App\Core\User\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ERPCopilotService
{
    private ERPContextBuilder $contextBuilder;

    private string $provider;

    private string $model;

    private string $apiKey;

    private int $maxMessageLength = 5000;

    private int $timeout = 20;

    public function __construct(ERPContextBuilder $contextBuilder, private AIChatProviderChain $chain)
    {
        $this->contextBuilder = $contextBuilder;

        // The assistant no longer binds to a single provider/key at construction —
        // the provider chain (config/ai.php → 'chat') decides at call time which free
        // provider to use, falling back to paid OpenAI only as a last resort. We keep
        // a nominal model label for audit rows; the chain reports the real one used.
        $this->provider = 'chain';
        $this->model = 'chain';
        $this->apiKey = '';
    }

    public function chat(string $message, ?string $sessionId = null): array
    {
        if (strlen($message) > $this->maxMessageLength) {
            throw ValidationException::withMessages([
                'message' => "Message exceeds {$this->maxMessageLength} characters.",
            ]);
        }

        $user = Auth::user();

        $key = 'ai-chat-'.$user->id;
        $maxAttempts = 20;
        $decaySeconds = 60;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw ValidationException::withMessages([
                'message' => 'Too many requests. Please slow down.',
            ]);
        }

        RateLimiter::hit($key, $decaySeconds);

        $sessionId = $sessionId ?? Str::uuid()->toString();

        // AI is metered per company. If the tenant is out of monthly tokens (and
        // the enforcement mode blocks), answer with a top-up prompt instead of
        // calling the model.
        $aiQuota = app(\App\Modules\Tenancy\Services\AiQuota::class);
        if ($aiQuota->blocked()) {
            return $this->quotaBlockedResponse($sessionId);
        }

        AiChatSession::firstOrCreate(
            ['session_id' => $sessionId],
            [
                'user_id' => $user->id,
                'title' => Str::limit($message, 60),
            ]
        );

        // Procedural "how do I …" questions are answered straight from the curated
        // how-to knowledge base (scoped to the user's active modules) — no LLM call,
        // so this cuts AI spend for the bulk of self-service/call-centre volume.
        $howTo = app(\App\Core\HowTo\Services\HowToKnowledgeService::class)->answerFor($message, $user);
        if ($howTo !== null) {
            AiChatMessage::create([
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'role' => 'user',
                'content' => $message,
                'metadata' => ['intent' => 'how_to'],
            ]);

            $assistant = AiChatMessage::create([
                'user_id' => $user->id,
                'session_id' => $sessionId,
                'role' => 'assistant',
                'content' => $howTo,
                'metadata' => ['source' => 'how_to'],
                'tokens_used' => 0,
            ]);

            return [
                'session_id' => $sessionId,
                'quota_warning' => null,
                'message' => [
                    'id' => $assistant->id,
                    'role' => 'assistant',
                    'content' => $assistant->content,
                    'metadata' => $assistant->metadata,
                    'timestamp' => $assistant->created_at->toISOString(),
                ],
            ];
        }

        $context = $this->contextBuilder->buildContext($message);

        $context['data'] = $this->compressData(
            $this->sanitizeData($context['data'] ?? [])
        );

        $history = $this->getOptimizedHistory($sessionId);

        AiChatMessage::create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'role' => 'user',
            'content' => $message,
            'metadata' => ['intent' => $context['intent'] ?? null],
        ]);

        $systemPrompt = $this->buildSystemPrompt($context, $user);
        $input = $this->buildInput($systemPrompt, $history, $message);

        $cacheKey = 'ai:'.md5($user->id.$message.json_encode($context['data']));

        $response = Cache::remember($cacheKey, 60, function () use ($input, $sessionId, $user) {
            return $this->callModel($input, $sessionId, $user);
        });

        $assistant = AiChatMessage::create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'role' => 'assistant',
            'content' => $response['text'],
            'metadata' => $response['metadata'],
            'tokens_used' => $response['tokens'],
        ]);

        // Meter this call against the company's monthly AI allowance.
        $this->recordUsage($user, $sessionId, $message, $response, implode(',', array_diff((array) ($context['intent'] ?? []), ['general'])) ?: 'general');

        // If the tenant is now low on tokens, surface a gentle warning the UI shows.
        $quotaWarning = null;
        if ($aiQuota->isEnforced() && ! $aiQuota->isUnlimited()) {
            $remaining = $aiQuota->remaining();
            if ($remaining <= 0) {
                $quotaWarning = 'You have used all your AI tokens for this month. Request or buy more to keep using AI.';
            } elseif ($remaining < ($response['tokens'] ?: 800) * 3) {
                $quotaWarning = 'You are running low on AI tokens this month (~' . number_format($remaining) . ' left). Consider topping up.';
            }
        }

        return [
            'session_id' => $sessionId,
            'quota_warning' => $quotaWarning,
            'message' => [
                'id' => $assistant->id,
                'role' => 'assistant',
                'content' => $assistant->content,
                'metadata' => $assistant->metadata,
                'timestamp' => $assistant->created_at->toISOString(),
            ],
        ];
    }

    /** Log this call's token usage against the user's company (drives the quota). */
    private function recordUsage(User $user, string $sessionId, string $message, array $response, string $category = 'general'): void
    {
        try {
            \App\Core\AI\Models\AiUsageLog::create([
                'user_id'      => $user->id,
                'company_id'   => \App\Modules\Sales\Support\SalesCompany::id() ?? $user->company_id,
                'session_id'   => $sessionId,
                'feature'      => 'ai_copilot',
                'category'     => $category,
                'provider'     => $response['metadata']['provider'] ?? null,
                'model_used'   => $response['metadata']['model'] ?? $this->model,
                'total_tokens' => (int) ($response['tokens'] ?? 0),
                'prompt_tokens' => (int) ($response['metadata']['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($response['metadata']['completion_tokens'] ?? 0),
                'cost_usd' => \App\Core\AI\Services\AiMeter::costUsd($response['metadata']['model'] ?? null,
                    (int) ($response['metadata']['prompt_tokens'] ?? 0), (int) ($response['metadata']['completion_tokens'] ?? 0)),
                'input_summary' => Str::limit($message, 180),
                'success'      => empty($response['metadata']['error']),
                'error_message' => $response['metadata']['error'] ?? null,
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AI usage logging failed', ['error' => $e->getMessage()]);
        }
    }

    /** Synthetic assistant reply when the company is out of AI tokens. */
    private function quotaBlockedResponse(string $sessionId): array
    {
        return [
            'session_id'    => $sessionId,
            'quota_blocked' => true,
            'quota_warning' => 'Your monthly AI token allowance is exhausted. Request or buy more to continue.',
            'message' => [
                'id'        => 0,
                'role'      => 'assistant',
                'content'   => "🚫 **AI is paused for this month.**\n\nYour company has used its entire monthly AI token allowance. To keep using the assistant you can **request more tokens** or **buy a top-up** from *Usage & Add-ons* in Settings, or ask a platform admin to grant more.",
                'metadata'  => ['quota_blocked' => true],
                'timestamp' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Call a language model through the free-first provider chain. Only reached for
     * open-ended questions the system couldn't already answer for free from the
     * how-to knowledge base or live ERP data.
     */
    private function callModel(array $input, string $sessionId, User $user): array
    {
        try {
            // recordUsage() writes this call down; the chain must not meter it a second time.
            return $this->chain->complete($input, ['meter' => false]);
        } catch (\Throwable $e) {
            Log::error('AI chat chain failed', [
                'error' => $e->getMessage(),
                'session_id' => $sessionId,
                'user_id' => $user->id,
            ]);

            return [
                'text' => '⚠️ AI service unavailable. Try again shortly.',
                'tokens' => 0,
                'metadata' => ['error' => $e->getMessage()],
            ];
        }
    }

    private function sanitizeData(array $data): array
    {
        return array_map(function ($item) {
            if (is_string($item)) {
                return strip_tags($item);
            }
            if (is_array($item)) {
                return $this->sanitizeData($item);
            }

            return $item;
        }, $data);
    }

    private function compressData(array $data): array
    {
        return array_map(fn ($items) => (is_array($items) && count($items) > 10)
            ? array_slice($items, 0, 10)
            : $items, $data);
    }

    private function getOptimizedHistory(string $sessionId): array
    {
        return AiChatMessage::where('session_id', $sessionId)
            ->latest()
            ->limit(10)
            ->get()
            ->reverse()
            ->map(fn ($msg) => [
                'role' => $msg->role,
                'content' => Str::limit($msg->content, 500),
            ])
            ->toArray();
    }

    public function getHistory(string $sessionId): array
    {
        return AiChatMessage::where('session_id', $sessionId)
            ->orderBy('created_at')
            ->get()
            ->map(fn ($msg) => [
                'id' => $msg->id,
                'role' => $msg->role,
                'content' => $msg->content,
                'timestamp' => $msg->created_at->toISOString(),
            ])
            ->toArray();
    }

    public function deleteSession(string $sessionId): void
    {
        AiChatSession::where('session_id', $sessionId)
            ->where('user_id', Auth::id())
            ->delete();

        AiChatMessage::where('session_id', $sessionId)
            ->where('user_id', Auth::id())
            ->delete();
    }

    public function getSessions(): array
    {
        return AiChatSession::where('user_id', Auth::id())
            ->latest()
            ->get()
            ->map(fn ($s) => [
                'session_id' => $s->session_id,
                'title' => $s->title,
                'created_at' => $s->created_at->toISOString(),
            ])
            ->toArray();
    }

    private function buildInput(string $systemPrompt, array $history, string $message): array
    {
        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ...$history,
            ['role' => 'user', 'content' => $message],
        ];
    }

    private function buildSystemPrompt(array $context, User $user): string
    {
        $roles = $user->roles;
        $firstRole = $roles ? $roles->first() : null;
        $roleName = $firstRole && $firstRole->name ? $firstRole->name : 'Employee';

        $dept = $user->department;
        $deptName = $dept && $dept->name ? $dept->name : 'Unassigned';

        $intent = $context['intent'] ?? 'unknown';
        if (is_array($intent)) {
            $intent = implode(', ', $intent);
        } else {
            $intent = (string) $intent;
        }

    
        $data = $this->safeJson($context['data'] ?? []);
        if (!is_string($data)) {
            $data = '{}';
        }

        // Module guardrail: the user may only be told about modules their company has
        // active and their role can access. Anything requested outside that list was
        // withheld from CONTEXT DATA (see _access_denied) and must not be answered.
        $accessibleModules = $context['accessible_modules'] ?? [];
        $accessibleList = $accessibleModules ? implode(', ', $accessibleModules) : 'none';
        $accessDenied = $context['data']['_access_denied'] ?? [];
        $deniedNote = $accessDenied
            ? 'The user asked about topics they cannot access: ' . implode(', ', $accessDenied)
              . '. Politely tell them that module is not available on their account and do not answer it.'
            : 'None.';


        return <<<PROMPT
    You are Mkhaya, the AI assistant inside Mkhaya Sales, helping the sales team with their daily work.

    USER CONTEXT:
    - Role: {$roleName}
    - Department: {$deptName}
    - User ID: {$user->id}

    INTENT:
    {$intent}

    ACCESSIBLE MODULES (only answer about these): {$accessibleList}
    ACCESS RESTRICTIONS: {$deniedNote}

    CONTEXT DATA:
    {$data}

    GUIDELINES:
    - Only use the provided system data to answer questions
    - Never fabricate or hallucinate information
    - Only answer about modules in ACCESSIBLE MODULES. If asked about anything else, say it isn't available on their account and suggest they contact their administrator — never guess or use general knowledge to answer it
    - Respect user permissions and access levels
    - For sales questions, answer only from the sales figures in CONTEXT DATA; say which period they cover; label forecasts as estimates; mark recommendations as AI-generated and for human review
    - Be concise and helpful
    - If data is insufficient, acknowledge it
    PROMPT;
    }

    private function safeJson(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}