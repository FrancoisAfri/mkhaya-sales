<?php

use App\Support\Database\TenantRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 6 — saved report definitions and export history (SRS §14.2–14.3, §21.5). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('name', 150);
            $t->jsonb('definition');            // dims, measure, compare, order, filters
            $t->boolean('shared')->default(false);
            $t->timestamps();
        });
        TenantRls::enable('saved_reports');

        Schema::create('report_exports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('report', 150);
            $t->string('format', 8);
            $t->jsonb('parameters')->nullable();
            $t->unsignedInteger('rows')->default(0);
            $t->timestamp('created_at');
        });
        TenantRls::enable('report_exports');
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
        Schema::dropIfExists('saved_reports');
    }
};
