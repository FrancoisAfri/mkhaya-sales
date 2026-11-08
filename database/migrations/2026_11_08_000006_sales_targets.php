<?php

use App\Support\Database\TenantRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 7 — targets, the approvals matrix, approvals and revisions (SRS TGT-001, §21.4). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('targets', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 190);
            $t->string('metric', 30);                   // net_value | gross_value | quantity | active_customers | transactions
            $t->string('method', 20)->default('fixed'); // fixed | minimum | maximum | range
            $t->string('period_type', 20);              // daily | weekly | monthly | quarterly | annual | financial_year | custom
            $t->date('start_date');
            $t->date('end_date');
            $t->decimal('target_value', 18, 2);
            $t->decimal('target_value_max', 18, 2)->nullable(); // range upper bound
            $t->jsonb('dimensions')->nullable();        // customer_id, product_id, category_id, region_id, …
            $t->string('status', 20)->default('draft'); // draft | submitted | approved | rejected | archived
            $t->unsignedInteger('version')->default(1);
            $t->unsignedSmallInteger('current_step')->default(0);
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users');
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'status', 'start_date', 'end_date']);
        });
        TenantRls::enable('targets');

        // The approvals matrix (setup): which targets need whose approval, in what order.
        Schema::create('target_approval_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 150);
            $t->string('metric', 30)->nullable();       // null = any metric
            $t->decimal('min_value', 18, 2)->nullable();
            $t->decimal('max_value', 18, 2)->nullable();
            $t->unsignedSmallInteger('step')->default(1);
            $t->string('approver_type', 20);            // role | user | creator_manager
            $t->string('approver_role', 100)->nullable();
            $t->foreignId('approver_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->boolean('allow_creator')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['company_id', 'is_active']);
        });
        TenantRls::enable('target_approval_rules');

        Schema::create('target_approvals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('target_id')->constrained('targets')->cascadeOnDelete();
            $t->unsignedInteger('version');
            $t->unsignedSmallInteger('step');
            $t->foreignId('rule_id')->nullable()->constrained('target_approval_rules')->nullOnDelete();
            $t->foreignId('decided_by')->constrained('users');
            $t->string('decision', 10);                 // approved | rejected
            $t->text('comment')->nullable();
            $t->timestamp('decided_at');
            $t->index(['target_id', 'version']);
        });
        TenantRls::enable('target_approvals');

        // Never overwrite a target without a record of what it was (§11.7).
        Schema::create('target_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('target_id')->constrained('targets')->cascadeOnDelete();
            $t->unsignedInteger('from_version');
            $t->decimal('previous_value', 18, 2);
            $t->decimal('new_value', 18, 2);
            $t->jsonb('previous')->nullable();          // the whole target as it was
            $t->text('reason');
            $t->foreignId('revised_by')->constrained('users');
            $t->timestamp('created_at');
        });
        TenantRls::enable('target_revisions');
    }

    public function down(): void
    {
        foreach (['target_revisions', 'target_approvals', 'target_approval_rules', 'targets'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
