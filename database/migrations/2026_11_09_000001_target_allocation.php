<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Release 2 — parent → child target allocation (SRS §11.5). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('targets', function (Blueprint $t) {
            $t->foreignId('parent_id')->nullable()->after('id')->constrained('targets')->nullOnDelete();
            $t->string('allocation_method', 20)->nullable(); // equal | percentage | historical | manual
            $t->index(['company_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('targets', function (Blueprint $t) {
            $t->dropConstrainedForeignId('parent_id');
            $t->dropColumn('allocation_method');
        });
    }
};
