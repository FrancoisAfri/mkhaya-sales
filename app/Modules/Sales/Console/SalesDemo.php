<?php

namespace App\Modules\Sales\Console;

use App\Modules\Sales\Models;
use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Services\CompanyProvisioner;
use App\Modules\Tenancy\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A separate demonstration company with 26 months of realistic food-distribution sales, a file
 * layout, targets and an approvals matrix — so every screen can be shown without touching a real
 * client's tenant. Re-running replaces the demo sales.
 */
class SalesDemo extends Command
{
    protected $signature = 'sales:demo {--email=demo@salesdemo.test} {--password=} {--master-only : Refresh groups, categories, products and targets without touching sales}';

    protected $description = 'Create or refresh the "Demo Foods" demonstration company';

    public function handle(CompanyProvisioner $provisioner, TenantContext $tenants): int
    {
        $company = Company::where('name', 'Demo Foods (Pty) Ltd')->first();
        $password = $this->option('password') ?: Str::password(14, symbols: false);
        if (! $company) {
            $r = $provisioner->provision(['company_name' => 'Demo Foods (Pty) Ltd', 'email' => $this->option('email'), 'password' => $password,
                'admin_name' => 'Demo Admin', 'plan' => null, 'trial_days' => 0]);
            $r['user']->assignRole('organisation administrator');
            $company = $r['company'];
            $this->warn("Login: {$this->option('email')} / {$password}");
        }
        $admin = \App\Core\User\Models\User::where('company_id', $company->id)->orderBy('id')->first();

        $tenants->run($company->id, function () use ($company, $admin) {
            if ($this->option('master-only')) {
                $this->masterData($company->id);
                $this->targets($company->id, $admin->id);
            $this->trade($company->id);
                $this->trade($company->id);

                return;
            }
            DB::table('sales_records')->where('company_id', $company->id)->delete();
            $regions = collect(['Gauteng', 'Western Cape', 'KwaZulu-Natal', 'Eastern Cape'])->map(fn ($n) => Models\Region::firstOrCreate(['name' => $n], ['code' => strtoupper(substr($n, 0, 3))]));
            $cats = collect(['Pizza', 'Frozen Meals', 'Cakes'])->map(fn ($n) => Models\ProductCategory::firstOrCreate(['name' => $n]));
            $brands = collect(['Oven Gold', 'Sweet Spoon', 'Crust & Co'])->map(fn ($n) => Models\ProductBrand::firstOrCreate(['name' => $n]));

            $products = collect(range(1, 18))->map(fn ($i) => Models\Product::updateOrCreate(['product_code' => sprintf('P%03d', $i)], [
                'product_name' => self::PRODUCTS[$i - 1][0], 'brand_id' => $brands[$i % 3]->id, 'is_active' => true, 'needs_review' => false,
            ]));
            $customers = collect(range(1, 24))->map(fn ($i) => Models\Customer::updateOrCreate(['customer_code' => sprintf('C%04d', $i)], [
                'customer_name' => ['Spar', 'Checkers', 'Pick n Pay', 'Makro', 'Food Lover\'s', 'Boxer', 'Shoprite', 'Woolworths'][$i % 8] . ' ' . ['Rosebank', 'Fourways', 'Claremont', 'Umhlanga', 'Gqeberha', 'Sandton', 'Durbanville', 'Midrand'][($i * 3) % 8] . ($i > 16 ? ' II' : ''),
                'region_id' => $regions[$i % 4]->id, 'is_active' => true, 'needs_review' => false,
                ...self::location(['Rosebank', 'Fourways', 'Claremont', 'Umhlanga', 'Gqeberha', 'Sandton', 'Durbanville', 'Midrand'][($i * 3) % 8], $i),
            ]));

            $this->masterData($company->id);
            $products = $products->map->fresh();
            $end = CarbonImmutable::today();
            $rows = [];
            mt_srand(42);
            for ($d = $end->subMonths(26)->startOfMonth(); $d->lte($end); $d = $d->addDay()) {
                if ($d->isSunday()) {
                    continue;
                }
                $season = 1 + 0.35 * sin(($d->month - 3) / 12 * 2 * M_PI) + ($d->month === 12 ? 0.4 : 0); // festive peak
                $trend = 1 + 0.08 * $d->diffInMonths($end->subMonths(26)) / 12;
                foreach ($customers->random(9) as $c) {
                    $p = $products->random();
                    $declining = in_array($c->customer_code, ['C0003', 'C0011'], true) && $d->gt($end->subMonths(4));
                    $growing = $p->category_id === $cats[0]->id;
                    $qty = max(1, (int) round(mt_rand(4, 40) * $season * $trend * ($declining ? 0.35 : 1) * ($growing ? 1.3 : 1)));
                    $price = 30 + ($p->id % 7) * 12;
                    $rows[] = ['company_id' => $c->company_id, 'transaction_date' => $d->toDateString(), 'customer_id' => $c->id, 'product_id' => $p->id,
                        'region_id' => $c->region_id, 'quantity' => $qty, 'unit_price' => $price, 'gross_value' => $qty * $price, 'discount' => 0,
                        'net_value' => $qty * $price, 'currency' => 'ZAR', 'row_key' => Str::uuid()->toString(), 'status' => 'active',
                        'created_at' => now(), 'updated_at' => now()];
                }
                if (count($rows) >= 1000) {
                    DB::table('sales_records')->insert($rows);
                    $rows = [];
                }
            }
            DB::table('sales_records')->insert($rows);

            Models\ReportLayout::firstOrCreate(['name' => 'Distributor daily sales'], ['target' => 'sales', 'ingestion_mode' => 'append', 'created_by' => $admin->id, 'mapping' => [
                ['source' => 'Inv Date', 'field' => 'transaction_date'], ['source' => 'Acc No', 'field' => 'customer_code'], ['source' => 'Acc Name', 'field' => 'customer_name'],
                ['source' => 'Material', 'field' => 'product_code'], ['source' => 'Material Desc', 'field' => 'product_name'], ['source' => 'Cases', 'field' => 'quantity'],
                ['source' => 'Nett Value', 'field' => 'net_value'], ['source' => 'Depot', 'field' => 'distribution_centre'], ['source' => 'Promo Flag', 'field' => 'attr:promo_flag'],
            ]]);
            Models\TargetApprovalRule::firstOrCreate(['name' => 'Sales managers sign off'], ['step' => 1, 'approver_type' => 'role', 'approver_role' => 'sales manager']);
            Models\TargetApprovalRule::firstOrCreate(['name' => 'Executive for targets above R1m'], ['step' => 2, 'min_value' => 1000000, 'approver_type' => 'role', 'approver_role' => 'administrator']);

            $this->targets($company->id, $admin->id);
        });

        $this->info('Demo Foods ready: ' . DB::table('sales_records')->where('company_id', $company->id)->count() . ' sales rows.');

        return self::SUCCESS;
    }

    /** Demo products: name and category (Dr. Oetker-style frozen pizza, frozen meals and cakes). */
    public const PRODUCTS = [
        ['Pizza base 2-pack', 'Pizza'], ['Beef lasagne 400g', 'Frozen Meals'], ['Butter chicken 400g', 'Frozen Meals'], ['Macaroni cheese 350g', 'Frozen Meals'],
        ['Chocolate cake', 'Cakes'], ['Vanilla sponge cake', 'Cakes'], ['Carrot cake', 'Cakes'], ['Spaghetti bolognese 400g', 'Frozen Meals'],
        ['Cottage pie 400g', 'Frozen Meals'], ['Chicken curry and rice 400g', 'Frozen Meals'], ['Ristorante Margherita pizza', 'Pizza'], ['Ristorante Pepperoni pizza', 'Pizza'],
        ['Pizza sauce', 'Pizza'], ['Baked cheesecake', 'Cakes'], ['Blueberry muffins 6-pack', 'Cakes'], ['Pizza Tradizionale Funghi', 'Pizza'],
        ['Four cheese pizza', 'Pizza'], ['Chocolate brownies', 'Cakes'],
    ];

    /** Retail groups by store banner; everything else is an independent. */
    public const GROUPS = ['Pick n Pay' => 'Pick n Pay Group', 'Boxer' => 'Pick n Pay Group', 'Shoprite' => 'Shoprite Checkers Group',
        'Checkers' => 'Shoprite Checkers Group', 'Spar' => 'Spar Group'];

    /** Groups, categories and product names as agreed for the demo (meeting 2026-11-09). Sales rows are untouched. */
    private function masterData(int $companyId): void
    {
        $groups = collect(['Pick n Pay Group', 'Shoprite Checkers Group', 'Spar Group', 'Other Independents'])
            ->mapWithKeys(fn ($n) => [$n => Models\CustomerGroup::firstOrCreate(['name' => $n])->id]);
        foreach (Models\Customer::where('company_id', $companyId)->get() as $c) {
            $banner = collect(array_keys(self::GROUPS))->first(fn ($b) => str_starts_with($c->customer_name, $b));
            $c->update(['customer_group_id' => $groups[$banner ? self::GROUPS[$banner] : 'Other Independents']]);
        }
        Models\CustomerGroup::whereNotIn('id', $groups->values())->delete();

        $cats = collect(['Pizza', 'Frozen Meals', 'Cakes'])->mapWithKeys(fn ($n) => [$n => Models\ProductCategory::firstOrCreate(['name' => $n])->id]);
        foreach (self::PRODUCTS as $i => [$name, $cat]) {
            Models\Product::where('product_code', sprintf('P%03d', $i + 1))->update(['product_name' => $name, 'category_id' => $cats[$cat]]);
        }
        Models\Target::whereNotNull('dimensions')->get()->filter(fn ($t) => isset($t->dimensions['category_id']) && ! $cats->contains($t->dimensions['category_id']))->each->delete();
        Models\ProductCategory::whereNotIn('id', $cats->values())->delete();
        \App\Modules\Sales\Services\BI\BIQueryService::invalidate($companyId);
    }

    /**
     * Monthly company-wide targets from January to this month, in value (rand) and volume (cases):
     * each past month a little above or below what was sold, this month 6% above last month.
     */
    private function targets(int $companyId, int $adminId): void
    {
        $end = CarbonImmutable::parse(DB::table('sales_records')->where('company_id', $companyId)->where('status', 'active')->max('transaction_date') ?? 'today');
        $sum = fn ($col, $m) => (float) DB::table('sales_records')->where('company_id', $companyId)->where('status', 'active')
            ->whereBetween('transaction_date', [$m->startOfMonth()->toDateString(), $m->endOfMonth()->toDateString()])->sum($col);
        $factors = [1.04, 0.97, 1.06, 1.02, 0.95, 1.08, 1.01, 0.98, 1.05, 1.03, 1.06, 1.04];
        for ($m = $end->startOfYear(); $m->lte($end); $m = $m->addMonth()) {
            $isNow = $m->format('Y-m') === $end->format('Y-m');
            $basis = $isNow ? $m->subMonthNoOverflow() : $m;
            $f = $isNow ? 1.06 : $factors[$m->month - 1];
            foreach ([['National net sales — ', 'net_value', round($sum('net_value', $basis) * $f, -3)], ['National volume (cases) — ', 'quantity', round($sum('quantity', $basis) * $f, -1)]] as [$label, $metric, $value]) {
                Models\Target::updateOrCreate(['name' => $label . $m->format('F Y')], ['metric' => $metric, 'method' => 'fixed', 'period_type' => 'monthly',
                    'start_date' => $m->startOfMonth(), 'end_date' => $m->endOfMonth(), 'target_value' => $value, 'dimensions' => null,
                    'status' => 'approved', 'approved_at' => now(), 'created_by' => $adminId]);
            }
        }
        \App\Modules\Sales\Services\BI\BIQueryService::invalidate($companyId);
    }

    /** Demo stock on hand, three promotions and a price list (gap 7), sized from the demo's own sales. */
    private function trade(int $companyId): void
    {
        $last = CarbonImmutable::parse(DB::table('sales_records')->where('company_id', $companyId)->where('status', 'active')->max('transaction_date') ?? 'today');
        $products = Models\Product::where('company_id', $companyId)->orderBy('product_code')->get();
        $dc = Models\DistributionCentre::firstOrCreate(['name' => 'Midrand DC']);
        foreach (['stock_levels', 'promotions', 'price_lists'] as $t) {
            DB::table($t)->where('company_id', $companyId)->delete();
        }
        foreach ($products as $i => $p) {
            $daily = (float) DB::table('sales_records')->where('company_id', $companyId)->where('product_id', $p->id)
                ->whereBetween('transaction_date', [$last->subDays(27)->toDateString(), $last->toDateString()])->sum('quantity') / 28;
            $cover = [3, 9, 21, 35, 75, 18][$i % 6];   // a mix of short, healthy and long cover
            DB::table('stock_levels')->insert(['company_id' => $companyId, 'snapshot_date' => $last->toDateString(), 'product_id' => $p->id, 'distribution_centre_id' => $dc->id,
                'cases_on_hand' => round($daily * $cover), 'created_at' => now(), 'updated_at' => now()]);
            $avg = (float) DB::table('sales_records')->where('company_id', $companyId)->where('product_id', $p->id)->selectRaw('sum(net_value) / nullif(sum(quantity),0) as p')->value('p');
            DB::table('price_lists')->insert(['company_id' => $companyId, 'product_id' => $p->id, 'customer_group_id' => null, 'price_per_case' => round(($avg ?: 50) * [1.0, 1.04, 1.12][$i % 3], 2),
                'valid_from' => $last->startOfYear()->toDateString(), 'valid_to' => null, 'created_at' => now(), 'updated_at' => now()]);
        }
        $cat = fn ($n) => Models\ProductCategory::where('name', $n)->value('id');
        $grp = fn ($n) => Models\CustomerGroup::where('name', $n)->value('id');
        foreach ([
            ['Pizza month at Spar', $last->subDays(20), $last->subDays(6), null, $cat('Pizza'), null, $grp('Spar Group'), 15, 'Buy 2 save 15%'],
            ['Cakes festive launch', $last->subDays(5), $last->addDays(25), null, $cat('Cakes'), null, null, 10, 'Shelf price -10%'],
            ['Frozen meals 3 for 2', $last->subMonths(2)->startOfMonth(), $last->subMonths(2)->startOfMonth()->addDays(13), null, $cat('Frozen Meals'), null, $grp('Shoprite Checkers Group'), 33, '3 for 2'],
        ] as [$name, $s, $e, $prod, $category, $cust, $group, $disc, $mech]) {
            DB::table('promotions')->insert(['company_id' => $companyId, 'name' => $name, 'start_date' => $s->toDateString(), 'end_date' => $e->toDateString(), 'product_id' => $prod,
                'category_id' => $category, 'customer_id' => $cust, 'customer_group_id' => $group, 'discount_pct' => $disc, 'mechanic' => $mech, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** Real suburb coordinates (with a small offset per store) so the demo maps have something to show. */
    public static function location(string $suburb, int $i): array
    {
        $at = [
            'Rosebank' => ['Johannesburg', 'Gauteng', -26.1467, 28.0436], 'Fourways' => ['Johannesburg', 'Gauteng', -26.0205, 28.0067],
            'Claremont' => ['Cape Town', 'Western Cape', -33.9806, 18.4653], 'Umhlanga' => ['Durban', 'KwaZulu-Natal', -29.7270, 31.0849],
            'Gqeberha' => ['Gqeberha', 'Eastern Cape', -33.9608, 25.6022], 'Sandton' => ['Johannesburg', 'Gauteng', -26.1076, 28.0567],
            'Durbanville' => ['Cape Town', 'Western Cape', -33.8326, 18.6477], 'Midrand' => ['Midrand', 'Gauteng', -25.9992, 28.1263],
        ][$suburb] ?? ['Johannesburg', 'Gauteng', -26.2041, 28.0473];
        $jitter = fn ($n) => (($i * $n) % 17 - 8) / 1000;

        return ['city' => $at[0], 'province' => $at[1], 'latitude' => $at[2] + $jitter(7), 'longitude' => $at[3] + $jitter(11)];
    }
}
