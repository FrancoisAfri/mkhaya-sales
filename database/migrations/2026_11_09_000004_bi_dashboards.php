<?php

use App\Support\Database\TenantRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BI module — dashboards, bookmarks and custom measures (BI spec §4, §6.7, §7.3, §8.4).
 * Widgets and their grid positions live on the dashboard as validated JSON: a dashboard is saved
 * and shared as one unit, and every widget is re-validated server-side before it is queried.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bi_dashboards', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('name', 150);
            $t->string('description', 500)->nullable();
            $t->string('visibility', 20)->default('private');   // private | shared | organisation
            $t->jsonb('shared_roles')->nullable();
            $t->jsonb('shared_users')->nullable();
            $t->string('template_key', 60)->nullable();
            $t->string('role_landing', 60)->nullable();          // role this is the landing page for
            $t->jsonb('widgets');                                // [{id, type, title, config, x, y, w, h}]
            $t->jsonb('filters')->nullable();                    // dashboard-level filter defaults
            $t->timestamps();
            $t->index(['company_id', 'visibility']);
        });
        TenantRls::enable('bi_dashboards');

        Schema::create('bi_bookmarks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('dashboard_id')->constrained('bi_dashboards')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('name', 120);
            $t->jsonb('state');
            $t->timestamps();
        });
        TenantRls::enable('bi_bookmarks');

        Schema::create('bi_measures', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('key', 60);
            $t->string('label', 120);
            $t->string('formula', 255);                          // over base measures, via Formula
            $t->string('format', 12)->default('number');         // currency | number | percent
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'key']);
        });
        TenantRls::enable('bi_measures');
    }

    public function down(): void
    {
        foreach (['bi_measures', 'bi_bookmarks', 'bi_dashboards'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
