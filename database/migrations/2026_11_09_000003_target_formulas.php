<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Release 3 — formula, growth and composite targets (SRS §11.1, §11.4). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('targets', function (Blueprint $t) {
            $t->string('formula', 255)->nullable();               // e.g. net_value / quantity
            $t->decimal('weight', 8, 2)->default(1);              // share in a composite parent
        });
    }

    public function down(): void
    {
        Schema::table('targets', fn (Blueprint $t) => $t->dropColumn(['formula', 'weight']));
    }
};
