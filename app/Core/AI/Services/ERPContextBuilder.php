<?php

namespace App\Core\AI\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The facts the AI assistant may answer from, read live from the database.
 *
 * Mkhaya Sales starts with the platform core only — people, roles, organisation. Each new sales
 * module adds an intent here (keywords → module code → a data method), gated by module access so
 * the assistant never answers about something the user cannot open.
 */
class ERPContextBuilder
{
    public function buildContext(string $question): array
    {
        $intent = $this->classifyIntent($question);
        $accessible = $this->accessibleModuleCodes();

        return [
            'intent'             => $intent,
            'data'               => $this->gatherData($intent, $accessible),
            'schema'             => [],
            'timestamp'          => now()->toISOString(),
            'user_role'          => optional(Auth::user())->roles?->first()?->name ?? 'user',
            'accessible_modules' => $accessible,
        ];
    }

    /** @return array<int, string> module code_names the current user may be told about */
    private function accessibleModuleCodes(): array
    {
        $user = Auth::user();
        if (! $user) {
            return [];
        }

        if (($user->is_platform_admin ?? false) || ($user->is_master_admin ?? false)) {
            return DB::table('security_modules')->where('active', 1)->pluck('code_name')->all();
        }

        return DB::table('security_module_access')
            ->join('security_modules', 'security_module_access.module_id', '=', 'security_modules.id')
            ->where('security_module_access.user_id', $user->id)
            ->where('security_module_access.active', 1)
            ->where('security_modules.active', 1)
            ->pluck('security_modules.code_name')
            ->all();
    }

    /** Intent → the module that must be accessible before its data is gathered. */
    private function intentModuleMap(): array
    {
        return [
            'people' => 'user_management',
            'sales' => 'sales_overview',
        ];
    }

    private function classifyIntent(string $question): array
    {
        $q = strtolower($question);
        $map = [
            'people' => ['user', 'people', 'staff', 'role', 'reports to', 'department', 'division'],
            'sales' => ['sale', 'revenue', 'customer', 'product', 'target', 'top', 'declin', 'grow', 'month', 'year', 'forecast', 'brand', 'category', 'region', 'perform', 'compare', 'best', 'worst'],
        ];

        $intents = [];
        foreach ($map as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($q, $keyword)) {
                    $intents[] = $intent;
                    break;
                }
            }
        }
        $intents[] = 'general';

        return array_values(array_unique($intents));
    }

    private function gatherData(array $intents, array $accessible): array
    {
        $data = [];
        $skipped = [];
        $map = $this->intentModuleMap();

        foreach ($intents as $intent) {
            $module = $map[$intent] ?? null;
            if ($module !== null && ! in_array($module, $accessible, true)) {
                $skipped[] = $intent;
                continue;
            }

            try {
                $data[$intent] = match ($intent) {
                    'people'  => $this->getPeopleData(),
                    'sales'   => $this->getSalesData(),
                    'general' => $this->getGeneralOverview(),
                    default   => [],
                };
            } catch (\Throwable $e) {
                Log::warning("AI context gather failed for intent [{$intent}]: {$e->getMessage()}");
                $data[$intent] = [];
            }
        }

        if ($skipped) {
            $data['_access_denied'] = $skipped;
        }

        return $data;
    }

    /** Users by role and by organisation unit — names and work details only, never ID numbers. */
    private function getPeopleData(): array
    {
        return [
            'by_role' => DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_type', 'like', '%User')
                ->select('roles.name', DB::raw('count(*) as total'))
                ->groupBy('roles.name')
                ->get()->toArray(),
            'by_unit' => DB::table('company_unit_user_pivots as p')
                ->join('company_units as u', 'u.id', '=', 'p.company_id')
                ->select('u.name', 'u.type', DB::raw('count(*) as total'))
                ->groupBy('u.name', 'u.type')
                ->get()->toArray(),
            'people' => DB::table('hr_people as p')
                ->leftJoin('hr_people as m', 'm.id', '=', 'p.manager_id')
                ->leftJoin('hr_positions as pos', 'pos.id', '=', 'p.position_id')
                ->where('p.status', 1)
                ->orderBy('p.first_name')
                ->limit(50)
                ->get([
                    'p.first_name', 'p.surname', 'p.email', 'p.cell_number',
                    'pos.name as position',
                    DB::raw("trim(coalesce(m.first_name, '') || ' ' || coalesce(m.surname, '')) as reports_to"),
                ])->toArray(),
        ];
    }

    /**
     * Sales figures from the same services as the dashboards — tenant- and scope-restricted for this
     * user, aggregated (no row-level personal data), never free SQL (SRS AI-001 §16.2–16.3).
     */
    private function getSalesData(): array
    {
        $user = Auth::user();
        $analytics = app(\App\Modules\Sales\Services\SalesAnalytics::class);
        $last = $analytics->lastSaleDate();
        if (! $last || ! $user?->can('sales.view')) {
            return ['note' => $last ? 'User may not view sales.' : 'No sales data has been loaded yet.'];
        }
        $to = \Carbon\CarbonImmutable::parse($last);
        $month = ['from' => $to->startOfMonth()->toDateString(), 'to' => $to->toDateString()];
        $lastMonth = ['from' => $to->subMonthNoOverflow()->startOfMonth()->toDateString(), 'to' => $to->subMonthNoOverflow()->endOfMonth()->toDateString()];
        $slim = fn (array $rows, string $dim) => array_map(fn ($r) => array_intersect_key($r, array_flip([$dim, 'value', 'previous', 'growth', 'share'])), $rows);

        return [
            'data_up_to' => $last,
            'currency' => 'ZAR',
            'this_month_kpis' => $analytics->kpis($month, $user),
            'top_customers_this_month' => $slim($analytics->grouped($month, $user, ['customer'], 'net_value', 'previous_year', 5)['rows'], 'customer'),
            'top_products_this_month' => $slim($analytics->grouped($month, $user, ['product'], 'net_value', 'previous_year', 5)['rows'], 'product'),
            'top_products_last_month' => $slim($analytics->grouped($lastMonth, $user, ['product'], 'net_value', 'previous_year', 5)['rows'], 'product'),
            'declining_customers_vs_last_year' => $slim($analytics->grouped($month, $user, ['customer'], 'net_value', 'previous_year', 5, 'decline')['rows'], 'customer'),
            'fastest_growing_products' => $slim($analytics->grouped($month, $user, ['product'], 'net_value', 'previous_year', 5, 'growth')['rows'], 'product'),
            'categories_this_month' => $slim($analytics->grouped($month, $user, ['category'], 'net_value', 'previous_year', 8)['rows'], 'category'),
            'targets' => array_map(fn ($t) => ['name' => $t['name'], 'period' => $t['start_date'] . ' to ' . $t['end_date']] + array_intersect_key($t['performance'], array_flip(['target', 'actual', 'achievement', 'remaining', 'forecast', 'status'])),
                app(\App\Modules\Sales\Services\TargetService::class)->performanceList($month, $user)),
            'note' => 'Values are net sales in rand. Forecasts are estimates. growth is % vs the same period last year.',
        ];
    }

    private function getGeneralOverview(): array
    {
        return [
            'total_users'    => DB::table('users')->whereNull('deleted_at')->count(),
            'active_people'  => DB::table('hr_people')->where('status', 1)->count(),
            'inactive_people'=> DB::table('hr_people')->where('status', 0)->count(),
            'joined_last_30_days' => DB::table('hr_people')->where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }
}
