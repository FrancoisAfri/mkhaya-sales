<?php

use App\Support\Database\TenantRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Release 2 — configurable performance alerts (SRS NOT-001 §17.1, §17.3). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_alert_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->unique()->constrained('companies')->cascadeOnDelete();
            $t->jsonb('settings');
            $t->timestamps();
        });
        TenantRls::enable('sales_alert_settings');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_alert_settings');
    }
};
