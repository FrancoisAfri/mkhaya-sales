<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * For fixtures a tenant session could never write: another tenant's rows, and rows that belong to
 * nobody (the system's own accounts). Finance row-level security refuses those inside a tenant
 * session, which is the point; the platform writes them with no tenant set, and so does this.
 */
trait WritesAsPlatform
{
    protected function asPlatform(callable $write): mixed
    {
        $previous = DB::selectOne("SELECT current_setting('app.company_id', true) AS c")->c ?? '';
        DB::statement("SELECT set_config('app.company_id', '', false)");

        try {
            return $write();
        } finally {
            DB::statement("SELECT set_config('app.company_id', ?, false)", [(string) $previous]);
        }
    }
}
