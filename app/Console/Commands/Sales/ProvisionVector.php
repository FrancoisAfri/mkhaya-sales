<?php

namespace App\Console\Commands\Sales;

use App\Modules\Tenancy\Models\Company;
use App\Modules\Tenancy\Models\Subscription;
use App\Modules\Tenancy\Services\CompanyProvisioner;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The first customer and its contract (SRS §19.2), plus an optional second tenant used to prove
 * isolation (§3, acceptance scenario 20). Safe to run again: existing companies are left alone and
 * only the contract terms are brought up to date.
 */
class ProvisionVector extends Command
{
    protected $signature = 'sales:provision-vector
        {--admin-email=admin@vector.demo : Organisation administrator login}
        {--admin-name=Vector Administrator}
        {--start= : Contract start date (Y-m-d), default today}
        {--with-test-tenant : Also create "Isolation Test Co" for cross-tenant testing}';

    protected $description = 'Create the Vector Logistics tenant with its R35,000/month 12-month subscription';

    public const PLAN = 'vector_sales';

    private CompanyProvisioner $provisioner;

    public function handle(CompanyProvisioner $provisioner): int
    {
        $this->provisioner = $provisioner;
        DB::table('plans')->updateOrInsert(['code' => self::PLAN], [
            'name' => 'Vector Sales Intelligence',
            'price_monthly' => 35000,
            'regular_price_monthly' => 35000,
            'max_users' => null,   // named-user allowance to be confirmed (§19.2)
            'modules' => null,     // null = every module
            'is_active' => true,
            'sort' => 10,
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        $start = CarbonImmutable::parse($this->option('start') ?: now()->toDateString());
        $vector = $this->tenant('Vector Logistics', (string) $this->option('admin-email'), (string) $this->option('admin-name'));

        Subscription::updateOrCreate(['company_id' => $vector->id], [
            'plan_code' => self::PLAN,
            'status' => 'active',
            'current_period_start' => $start,
            'current_period_end' => $start->addMonth(),
            'monthly_price' => 35000,
            'contract_start' => $start,
            'contract_end' => $start->addMonths(12)->subDay(),
            'billing_frequency' => 'monthly',
            // Meeting 2026-11-09: no amount for additional work; 30-day payment terms; 3 calendar months' notice.
            'change_request_hourly_rate' => null,
            'payment_terms_days' => 30,
            'notice_months' => 3,
            'terms_note' => 'R35,000/month excl. VAT, 12 months. Hosting and initial implementation included; '
                .'historical migration up to 12 months. Report delivery by email (method 1) or from a secure central repository (method 2). '
                .'Payment within 30 days of invoice; either party may end the agreement on 3 calendar months\' written notice. '
                .'Additional work quoted separately on request. Named users, storage and AI allowances to be confirmed before production activation.',
        ]);
        $this->info("Vector Logistics: company #{$vector->id}, contract {$start->toDateString()} → {$start->addMonths(12)->subDay()->toDateString()}");

        if ($this->option('with-test-tenant')) {
            $other = $this->tenant('Isolation Test Co', 'admin@isolation-test.demo', 'Isolation Admin');
            $this->info("Isolation Test Co: company #{$other->id}");
        }

        return self::SUCCESS;
    }

    private function tenant(string $name, string $email, string $adminName): Company
    {
        $existing = Company::where('name', $name)->first();
        if ($existing) {
            return $existing;
        }

        $password = Str::password(16);
        $result = $this->provisioner->provision([
            'company_name' => $name,
            'email' => $email,
            'password' => $password,
            'admin_name' => $adminName,
            'plan' => self::PLAN,
            'trial_days' => 0,
        ]);
        $result['user']->assignRole('organisation administrator');
        $this->warn("  {$email} / {$password}  (shown once — change it after first login)");

        return $result['company'];
    }
}
