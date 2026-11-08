<?php

use App\Support\Database\TenantRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — product and customer master data (SRS MDM-001, MDM-002, §21.2).
 * Every table is tenant-owned: company_id, RLS, and uniqueness scoped to the company.
 */
return new class extends Migration
{
    /** Simple classification tables: code + name (+ optional parent for hierarchies). */
    private const LOOKUPS = [
        'product_categories' => true,   // parent_id → subcategories
        'product_brands' => false,
        'product_units' => false,
        'customer_groups' => false,
        'regions' => true,              // parent_id → provinces / areas
        'business_units' => false,
        'sales_teams' => false,
        'salespeople' => false,
        'distribution_centres' => false,
    ];

    public function up(): void
    {
        foreach (self::LOOKUPS as $name => $hierarchical) {
            Schema::create($name, function (Blueprint $t) use ($name, $hierarchical) {
                $t->id();
                $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                if ($hierarchical) {
                    $t->foreignId('parent_id')->nullable()->constrained($name)->nullOnDelete();
                }
                $t->string('code', 60)->nullable();
                $t->string('name', 190);
                if ($name === 'salespeople') {
                    $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                    $t->string('email', 190)->nullable();
                }
                if ($name === 'sales_teams') {
                    $t->unsignedBigInteger('region_id')->nullable();
                    $t->unsignedBigInteger('business_unit_id')->nullable();
                }
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->unique(['company_id', 'code']);
                $t->index(['company_id', 'name']);
            });
            TenantRls::enable($name);
        }

        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('product_code', 80);
            $t->string('product_name', 255);
            $t->string('alternative_code', 80)->nullable();
            $t->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $t->foreignId('brand_id')->nullable()->constrained('product_brands')->nullOnDelete();
            $t->foreignId('unit_id')->nullable()->constrained('product_units')->nullOnDelete();
            $t->string('pack_size', 60)->nullable();
            $t->string('currency', 3)->default('ZAR');
            $t->boolean('is_active')->default(true);
            $t->boolean('needs_review')->default(false); // created automatically from a sales file
            $t->jsonb('attributes')->nullable();          // client-specific extra fields
            $t->timestamps();
            $t->unique(['company_id', 'product_code']);
            $t->index(['company_id', 'category_id']);
            $t->index(['company_id', 'brand_id']);
        });
        TenantRls::enable('products');

        Schema::create('customers', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('customer_code', 80);
            $t->string('customer_name', 255);
            $t->string('alternative_name', 255)->nullable();
            $t->foreignId('customer_group_id')->nullable()->constrained('customer_groups')->nullOnDelete();
            $t->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $t->foreignId('business_unit_id')->nullable()->constrained('business_units')->nullOnDelete();
            $t->foreignId('sales_team_id')->nullable()->constrained('sales_teams')->nullOnDelete();
            $t->foreignId('salesperson_id')->nullable()->constrained('salespeople')->nullOnDelete();
            $t->foreignId('distribution_centre_id')->nullable()->constrained('distribution_centres')->nullOnDelete();
            $t->boolean('is_active')->default(true);
            $t->boolean('needs_review')->default(false);
            $t->jsonb('attributes')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'customer_code']);
            $t->index(['company_id', 'customer_group_id']);
            $t->index(['company_id', 'region_id']);
        });
        TenantRls::enable('customers');

        // Alternative codes / names used by source files for the same master record (§8.3).
        foreach (['product' => 'products', 'customer' => 'customers'] as $one => $table) {
            Schema::create("{$one}_aliases", function (Blueprint $t) use ($one, $table) {
                $t->id();
                $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $t->foreignId("{$one}_id")->constrained($table)->cascadeOnDelete();
                $t->string('alias_code', 80)->nullable();
                $t->string('alias_name', 255)->nullable();
                $t->timestamps();
                $t->unique(['company_id', 'alias_code']);
                $t->index(['company_id', 'alias_name']);
            });
            TenantRls::enable("{$one}_aliases");
        }
    }

    public function down(): void
    {
        foreach (['customer_aliases', 'product_aliases', 'customers', 'products'] as $t) {
            Schema::dropIfExists($t);
        }
        foreach (array_reverse(array_keys(self::LOOKUPS)) as $t) {
            Schema::dropIfExists($t);
        }
    }
};
