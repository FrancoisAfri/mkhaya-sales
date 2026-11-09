<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Contract terms agreed 2026-11-09: payment terms in days and the notice period in calendar months. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $t) {
            $t->unsignedSmallInteger('payment_terms_days')->nullable();
            $t->unsignedTinyInteger('notice_months')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', fn (Blueprint $t) => $t->dropColumn(['payment_terms_days', 'notice_months']));
    }
};
