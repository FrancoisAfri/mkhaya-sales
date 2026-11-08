<?php

namespace App\Core\AI\Controllers;

use App\Core\AI\Services\ERPCopilotService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AICopilotController extends Controller implements \Illuminate\Routing\Controllers\HasMiddleware
{
    public function __construct(private readonly ERPCopilotService $copilot) {}

    /**
     * The assistant answers from the company's live records (people, leave, assets, money).
     * It sits on every page, so the same rule that hides it decides who may call it.
     */
    public static function middleware(): array
    {
        return [
            new \Illuminate\Routing\Controllers\Middleware(function ($request, $next) {
                abort_unless(self::allowedFor($request->user()), 403);

                return $next($request);
            }),
        ];
    }

    /**
     * Staff of the business only. A subcontractor or client invited to a project, and a
     * reseller (who works above any one tenant), are outside the business and must not be
     * able to ask it about the company's people or money.
     */
    public static function allowedFor(?\App\Core\User\Models\User $user): bool
    {
        if ($user === null) {
            return false;
        }
        if ($user->is_platform_admin ?? false) {
            return true;
        }
        if (\App\Modules\Tenancy\Models\Reseller::where('user_id', $user->id)->where('status', 'active')->exists()) {
            return false;
        }

        return true;
    }

    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message'    => 'required|string|max:5000',
            'session_id' => 'nullable|string|max:50',
        ]);

        return response()->json(
            $this->copilot->chat(
                $request->input('message'),
                $request->input('session_id')
            )
        );
    }

    public function history(string $sessionId): JsonResponse
    {
        return response()->json([
            'messages' => $this->copilot->getHistory($sessionId),
        ]);
    }

    public function sessions(): JsonResponse
    {
        return response()->json([
            'sessions' => $this->copilot->getSessions(),
        ]);
    }

    public function newSession(): JsonResponse
    {
        return response()->json([
            'session_id' => Str::uuid()->toString(),
        ]);
    }

    public function deleteSession(string $sessionId): JsonResponse
    {
        $this->copilot->deleteSession($sessionId);

        return response()->json(['deleted' => true]);
    }

    /** Three headline sales figures for the assistant's welcome panel. */
    public function quickStats(): JsonResponse
    {
        $none = ['sales_mtd' => null, 'achievement' => null, 'customers' => null];
        $user = auth()->user();
        if (! \App\Modules\Sales\Support\SalesCompany::id() || ! $user?->can('sales.view')) {
            return response()->json($none);
        }
        try {
            $analytics = app(\App\Modules\Sales\Services\SalesAnalytics::class);
            $last = $analytics->lastSaleDate();
            if (! $last) {
                return response()->json($none);
            }
            $to = \Carbon\CarbonImmutable::parse($last);
            $f = ['from' => $to->startOfMonth()->toDateString(), 'to' => $to->toDateString()];
            $k = $analytics->kpis($f, $user);
            $t = app(\App\Modules\Sales\Services\TargetService::class)->summary($f, $user);
            $short = fn (float $v) => 'R ' . ($v >= 1e6 ? round($v / 1e6, 1) . 'm' : ($v >= 1e3 ? round($v / 1e3) . 'k' : round($v)));

            return response()->json([
                'sales_mtd' => $short((float) $k['mtd']),
                'achievement' => $t['org_achievement'] !== null ? $t['org_achievement'] . '%' : null,
                'customers' => number_format((int) $k['customers']),
            ]);
        } catch (\Throwable) {
            return response()->json($none);
        }
    }
}
