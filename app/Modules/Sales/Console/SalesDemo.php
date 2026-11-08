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
    protected $signature = 'sales:demo {--email=demo@salesdemo.test} {--password=}';

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
            DB::table('sales_records')->where('company_id', $company->id)->delete();
            $regions = collect(['Gauteng', 'Western Cape', 'KwaZulu-Natal', 'Eastern Cape'])->map(fn ($n) => Models\Region::firstOrCreate(['name' => $n], ['code' => strtoupper(substr($n, 0, 3))]));
            $groups = collect(['Modern trade', 'Wholesale', 'Independent', 'Food service'])->map(fn ($n) => Models\CustomerGroup::firstOrCreate(['name' => $n]));
            $cats = collect(['Baking', 'Desserts', 'Pizza', 'Decorations'])->map(fn ($n) => Models\ProductCategory::firstOrCreate(['name' => $n]));
            $brands = collect(['Oven Gold', 'Sweet Spoon', 'Crust & Co'])->map(fn ($n) => Models\ProductBrand::firstOrCreate(['name' => $n]));

            $products = collect(range(1, 18))->map(fn ($i) => Models\Product::updateOrCreate(['product_code' => sprintf('P%03d', $i)], [
                'product_name' => ['Pizza base 2-pack', 'Custard powder 1kg', 'Baking powder 100g', 'Vanilla essence', 'Cake mix chocolate', 'Cake mix vanilla',
                    'Icing sugar 500g', 'Sprinkles rainbow', 'Instant pudding caramel', 'Jelly strawberry', 'Yeast sachets', 'Cornflour 500g',
                    'Pizza sauce', 'Cheesecake mix', 'Muffin mix', 'Food colouring set', 'Gelatine 40g', 'Brownie mix'][$i - 1],
                'category_id' => $cats[$i % 4]->id, 'brand_id' => $brands[$i % 3]->id, 'is_active' => true, 'needs_review' => false,
            ]));
            $customers = collect(range(1, 24))->map(fn ($i) => Models\Customer::updateOrCreate(['customer_code' => sprintf('C%04d', $i)], [
                'customer_name' => ['Spar', 'Checkers', 'Pick n Pay', 'Makro', 'Food Lover\'s', 'Boxer', 'Shoprite', 'Woolworths'][$i % 8] . ' ' . ['Rosebank', 'Fourways', 'Claremont', 'Umhlanga', 'Gqeberha', 'Sandton', 'Durbanville', 'Midrand'][($i * 3) % 8] . ($i > 16 ? ' II' : ''),
                'customer_group_id' => $groups[$i % 4]->id, 'region_id' => $regions[$i % 4]->id, 'is_active' => true, 'needs_review' => false,
            ]));

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
                    $growing = $p->category_id === $cats[2]->id;
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

            $monthSales = (float) DB::table('sales_records')->where('company_id', $company->id)->whereBetween('transaction_date', [$end->subMonthNoOverflow()->startOfMonth(), $end->subMonthNoOverflow()->endOfMonth()])->sum('net_value');
            foreach ([
                ['National net sales — ' . $end->format('F Y'), 'net_value', round($monthSales * 1.06, -3), null],
                ['Pizza category — ' . $end->format('F Y'), 'net_value', round($monthSales * 0.3, -3), ['category_id' => $cats[2]->id]],
                ['Gauteng cases — ' . $end->format('F Y'), 'quantity', 2500, ['region_id' => $regions[0]->id]],
            ] as [$name, $metric, $value, $dims]) {
                Models\Target::updateOrCreate(['name' => $name], ['metric' => $metric, 'method' => 'fixed', 'period_type' => 'monthly', 'start_date' => $end->startOfMonth(),
                    'end_date' => $end->endOfMonth(), 'target_value' => $value, 'dimensions' => $dims, 'status' => 'approved', 'approved_at' => now(), 'created_by' => $admin->id]);
            }
        });

        $this->info('Demo Foods ready: ' . DB::table('sales_records')->where('company_id', $company->id)->count() . ' sales rows.');

        return self::SUCCESS;
    }
}
