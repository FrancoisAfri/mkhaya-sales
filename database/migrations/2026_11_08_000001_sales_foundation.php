<?php

use App\Support\Database\TenantRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — foundation (SRS ARC-001, IAM-001 §4.3, SUB-001 §19.1).
 */
return new class extends Migration
{
    public function up(): void
    {
        // A user restricted to particular regions, teams, business units or customer groups.
        // No rows for a user means no restriction. scope_id points into the dimension's own table,
        // which arrives with master data (phase 2) — hence no foreign key on it here.
        Schema::create('user_data_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope_type', 40); // region | sales_team | business_unit | customer_group
            $table->unsignedBigInteger('scope_id');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'user_id', 'scope_type', 'scope_id']);
            $table->index(['company_id', 'scope_type', 'scope_id']);
        });
        TenantRls::enable('user_data_scopes');

        // The commercial contract behind a subscription (§19.1 / §19.2).
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->decimal('monthly_price', 12, 2)->nullable();
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->string('billing_frequency', 20)->default('monthly');
            $table->unsignedInteger('named_users')->nullable();       // null = to be confirmed
            $table->unsignedInteger('storage_gb')->nullable();
            $table->unsignedInteger('integration_allowance')->nullable();
            $table->unsignedBigInteger('ai_token_allowance')->nullable();
            $table->decimal('change_request_hourly_rate', 10, 2)->nullable();
            $table->text('terms_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'monthly_price', 'contract_start', 'contract_end', 'billing_frequency', 'named_users',
                'storage_gb', 'integration_allowance', 'ai_token_allowance', 'change_request_hourly_rate', 'terms_note',
            ]);
        });
        Schema::dropIfExists('user_data_scopes');
    }
};
