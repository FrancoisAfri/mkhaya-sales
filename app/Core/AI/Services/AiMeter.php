<?php

namespace App\Core\AI\Services;

use App\Core\AI\Models\AiUsageLog;
use App\Modules\Tenancy\Services\CurrentCompany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * One place that writes an AI call to ai_usage_log, which drives the company's monthly AI quota.
 *
 * Many services call the provider chain directly rather than through an agent driver, and those
 * calls were never written down: article writing, SEO, social posts and the rest of Content
 * Marketing cost tokens that no quota saw. Every path now meters here. When a provider reports no
 * usage (some free OpenAI-compatible endpoints do not), the tokens are estimated at four characters
 * a token and the row says so.
 */
class AiMeter
{
    private static ?bool $hasEstimatedColumn = null;

    /** Roughly four characters a token, for text a provider gave no count for. */
    public static function estimate(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    /** US$ for a call, when the model's price is known (config ai.pricing, matched by prefix). */
    public static function costUsd(?string $model, int $prompt, int $completion): ?float
    {
        if (! $model) {
            return null;
        }
        foreach ((array) config('ai.pricing', []) as $prefix => [$in, $out]) {
            if (str_starts_with($model, $prefix)) {
                return round($prompt / 1_000_000 * $in + $completion / 1_000_000 * $out, 6);
            }
        }

        return null;
    }

    public function record(string $feature, ?string $model, int $prompt, int $completion, bool $estimated = false,
        bool $success = true, ?string $error = null, ?int $latencyMs = null, ?string $summary = null): void
    {
        try {
            $row = [
                'user_id'           => Auth::id(),
                'company_id'        => app(CurrentCompany::class)->id(),
                'feature'           => Str::limit($feature, 100, ''),
                'model_used'        => $model,
                'prompt_tokens'     => $prompt,
                'completion_tokens' => $completion,
                'total_tokens'      => $prompt + $completion,
                'cost_usd'          => self::costUsd($model, $prompt, $completion),
                'input_summary'     => $summary !== null ? Str::limit($summary, 180) : null,
                'latency_ms'        => $latencyMs,
                'success'           => $success,
                'error_message'     => $error,
            ];
            self::$hasEstimatedColumn ??= Schema::hasColumn('ai_usage_log', 'tokens_estimated');
            if (self::$hasEstimatedColumn) {
                $row['tokens_estimated'] = $estimated;
            }
            AiUsageLog::create($row);
        } catch (\Throwable) {
            // Metering must never break an AI feature.
        }
    }
}
