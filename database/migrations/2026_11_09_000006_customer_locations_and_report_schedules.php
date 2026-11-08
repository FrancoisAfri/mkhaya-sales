<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Customer locations for the maps, and email schedules on saved reports and dashboards. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->decimal('latitude', 10, 7)->nullable();
            $t->decimal('longitude', 10, 7)->nullable();
            $t->string('province', 60)->nullable();
            $t->string('city', 120)->nullable();
            $t->index(['company_id', 'province']);
        });

        Schema::create('report_schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name', 150);
            // What to send: a saved report, a standard report key, or a BI dashboard (as a PDF summary).
            $t->string('source', 20); // saved | standard | dashboard
            $t->string('source_ref', 80);
            $t->string('format', 10)->default('xlsx'); // xlsx | csv | pdf
            $t->string('period', 30)->default('last_month'); // mtd | last_month | ytd | last_7_days | yesterday
            $t->string('frequency', 10); // daily | weekly | monthly
            $t->unsignedTinyInteger('weekday')->nullable(); // 1 = Monday
            $t->unsignedTinyInteger('month_day')->nullable();
            $t->string('send_time', 5)->default('07:00');
            $t->json('recipients');
            $t->json('filters')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamp('last_sent_at')->nullable();
            $t->string('last_status', 20)->nullable();
            $t->text('last_error')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'is_active']);
        });
        \App\Support\Database\TenantRls::enable('report_schedules');
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
        Schema::table('customers', function (Blueprint $t) {
            $t->dropIndex(['company_id', 'province']);
            $t->dropColumn(['latitude', 'longitude', 'province', 'city']);
        });
    }
};
