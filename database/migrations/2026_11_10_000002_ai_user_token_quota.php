<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user AI allowance (2026-11-10). A company's monthly allowance is shared by its people; each
 * person may also have their own monthly ceiling so one heavy user cannot use it all.
 * companies.ai_user_token_quota = the default ceiling for every user (null = no per-user ceiling);
 * users.ai_token_quota = this person's own ceiling (null = the company default; 0 = no AI).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', fn (Blueprint $t) => $t->unsignedBigInteger('ai_user_token_quota')->nullable());
        Schema::table('users', fn (Blueprint $t) => $t->unsignedBigInteger('ai_token_quota')->nullable());
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('ai_token_quota'));
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('ai_user_token_quota'));
    }
};
