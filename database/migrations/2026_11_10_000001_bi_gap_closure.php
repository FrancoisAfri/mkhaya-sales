<?php

use App\Support\Database\TenantRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Gap review 2026-11-10: versions, certified dashboards, comments, alerts, share links, monthly summary, stock, promotions, prices. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bi_dashboards', function (Blueprint $t) {
            $t->boolean('certified')->default(false);
            $t->foreignId('certified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('certified_at')->nullable();
        });

        Schema::create('bi_dashboard_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('dashboard_id')->constrained('bi_dashboards')->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->string('name', 150);
            $t->jsonb('widgets');
            $t->foreignId('saved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('note', 200)->nullable();
            $t->timestamp('created_at')->nullable();
            $t->index(['dashboard_id', 'version']);
        });

        Schema::create('bi_comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('dashboard_id')->constrained('bi_dashboards')->cascadeOnDelete();
            $t->string('widget_id', 20)->nullable();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->jsonb('mentions')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->index(['dashboard_id', 'widget_id']);
        });

        Schema::create('bi_alerts', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name', 150);
            $t->string('measure', 60);
            $t->jsonb('filters')->nullable();
            $t->string('period', 20);          // yesterday | last_7_days | mtd | ytd
            $t->string('comparison', 20);      // value | vs_previous | vs_last_year | vs_target
            $t->string('operator', 10);        // above | below
            $t->decimal('threshold', 18, 2);
            $t->string('frequency', 10)->default('daily'); // daily | weekly
            $t->unsignedTinyInteger('weekday')->nullable();
            $t->string('run_time', 5)->default('07:00');
            $t->jsonb('channels');             // ["in_app","email"]
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_checked_at')->nullable();
            $t->timestamp('last_triggered_at')->nullable();
            $t->decimal('last_value', 18, 2)->nullable();
            $t->timestamps();
            $t->index(['company_id', 'is_active']);
        });

        Schema::create('bi_shares', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('dashboard_id')->constrained('bi_dashboards')->cascadeOnDelete();
            $t->string('token', 64)->unique();
            $t->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $t->jsonb('filters')->nullable();
            $t->timestamp('expires_at');
            $t->timestamp('revoked_at')->nullable();
            $t->unsignedInteger('views')->default(0);
            $t->timestamp('last_viewed_at')->nullable();
            $t->timestamps();
        });

        // Pre-summarised sales by month and every dimension the engine groups by (gap 5).
        Schema::create('sales_monthly_summary', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->date('transaction_date');      // first day of the month — same column name as sales_records
            foreach (['customer_id', 'product_id', 'region_id', 'business_unit_id', 'sales_team_id', 'salesperson_id', 'distribution_centre_id', 'layout_id'] as $c) {
                $t->unsignedBigInteger($c)->nullable();
            }
            $t->decimal('net_value', 18, 2)->default(0);
            $t->decimal('gross_value', 18, 2)->default(0);
            $t->decimal('quantity', 18, 3)->default(0);
            $t->decimal('discount', 18, 2)->default(0);
            $t->unsignedInteger('lines')->default(0);
            $t->string('status', 10)->default('active');
            $t->index(['company_id', 'status', 'transaction_date']);
            $t->index(['company_id', 'customer_id', 'transaction_date']);
            $t->index(['company_id', 'product_id', 'transaction_date']);
        });

        // Data beside sales (gap 7).
        Schema::create('stock_levels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->date('snapshot_date');
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('distribution_centre_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('cases_on_hand', 18, 3);
            $t->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->index(['company_id', 'snapshot_date']);
            $t->index(['company_id', 'product_id', 'snapshot_date']);
        });
        Schema::create('promotions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('name', 150);
            $t->date('start_date');
            $t->date('end_date');
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $t->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('customer_group_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('discount_pct', 6, 2)->nullable();
            $t->string('mechanic', 150)->nullable();
            $t->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->index(['company_id', 'start_date', 'end_date']);
        });
        Schema::create('price_lists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('customer_group_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('price_per_case', 14, 2);
            $t->date('valid_from');
            $t->date('valid_to')->nullable();
            $t->foreignId('import_batch_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();
            $t->index(['company_id', 'product_id', 'valid_from']);
        });

        foreach (['bi_dashboard_versions', 'bi_comments', 'bi_alerts', 'bi_shares', 'sales_monthly_summary', 'stock_levels', 'promotions', 'price_lists'] as $table) {
            TenantRls::enable($table);
        }
    }

    public function down(): void
    {
        foreach (['price_lists', 'promotions', 'stock_levels', 'sales_monthly_summary', 'bi_shares', 'bi_alerts', 'bi_comments', 'bi_dashboard_versions'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('bi_dashboards', function (Blueprint $t) {
            $t->dropConstrainedForeignId('certified_by');
            $t->dropColumn(['certified', 'certified_at']);
        });
    }
};
