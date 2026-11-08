<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** AI usage monitoring (SRS §16.4): provider and request category alongside user, model, tokens, cost, outcome. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage_log', function (Blueprint $t) {
            $t->string('provider', 40)->nullable();
            $t->string('category', 40)->nullable();
            $t->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage_log', fn (Blueprint $t) => $t->dropColumn(['provider', 'category']));
    }
};
