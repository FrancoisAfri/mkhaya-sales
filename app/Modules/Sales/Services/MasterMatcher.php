<?php

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Models;
use Illuminate\Support\Facades\DB;

/**
 * Finds the master record a source row refers to: by code, then alias code, then alias name,
 * then exact name (SRS §8.3). Cached for the life of one import. Creates nothing unless asked.
 */
class MasterMatcher
{
    private array $cache = [];

    public function product(?string $code, ?string $name): ?int
    {
        return $this->match('products', 'product', 'product_code', 'product_name', $code, $name);
    }

    public function customer(?string $code, ?string $name): ?int
    {
        return $this->match('customers', 'customer', 'customer_code', 'customer_name', $code, $name);
    }

    /** A classification (region, team…) by code or name; created when $create. */
    public function lookup(string $table, ?string $value, bool $create = false, array $extra = []): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $k = "{$table}|" . mb_strtolower($value);
        if (array_key_exists($k, $this->cache)) {
            return $this->cache[$k];
        }

        $id = DB::table($table)->where('company_id', $this->company())
            ->where(fn ($q) => $q->whereRaw('lower(code) = ?', [mb_strtolower($value)])->orWhereRaw('lower(name) = ?', [mb_strtolower($value)]))
            ->value('id');

        if (! $id && $create) {
            $id = DB::table($table)->insertGetId(array_merge([
                'company_id' => $this->company(), 'name' => $value, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ], $extra));
        }

        return $this->cache[$k] = $id ?: null;
    }

    public function remember(string $kind, ?string $code, ?string $name, int $id): void
    {
        foreach (array_filter([$code, $name]) as $v) {
            $this->cache["{$kind}|" . mb_strtolower(trim($v))] = $id;
        }
    }

    private function match(string $table, string $kind, string $codeCol, string $nameCol, ?string $code, ?string $name): ?int
    {
        $code = trim((string) $code);
        $name = trim((string) $name);
        foreach (array_filter([$code, $name]) as $v) {
            if (array_key_exists("{$kind}|" . mb_strtolower($v), $this->cache)) {
                return $this->cache["{$kind}|" . mb_strtolower($v)];
            }
        }

        $company = $this->company();
        $id = null;
        if ($code !== '') {
            $id = DB::table($table)->where('company_id', $company)->whereRaw("lower({$codeCol}) = ?", [mb_strtolower($code)])->value('id')
                ?? DB::table("{$kind}_aliases")->where('company_id', $company)->whereRaw('lower(alias_code) = ?', [mb_strtolower($code)])->value("{$kind}_id");
        }
        if (! $id && $name !== '') {
            $id = DB::table("{$kind}_aliases")->where('company_id', $company)->whereRaw('lower(alias_name) = ?', [mb_strtolower($name)])->value("{$kind}_id")
                ?? ($code === '' ? DB::table($table)->where('company_id', $company)->whereRaw("lower({$nameCol}) = ?", [mb_strtolower($name)])->value('id') : null);
        }

        if ($id) {
            $this->remember($kind, $code, $name, $id);
        }

        return $id;
    }

    private function company(): int
    {
        return (int) app(\App\Modules\Tenancy\Services\CurrentCompany::class)->id();
    }
}
