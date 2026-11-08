<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The Sales Intelligence menu (SRS §26.2): one category, five modules, at most five entries each.
 * Idempotent — paths are the keys. Each page also checks its own permission, so a menu entry a
 * user can see but not use still answers 403 rather than showing data.
 */
class SalesNavigationSeeder extends Seeder
{
    public const MODULES = [
        ['code' => 'sales_overview', 'name' => 'Overview', 'icon' => 'chart-pie', 'path' => '/sales/bi', 'ribbons' => [
            ['My dashboards', 'sales/bi'],
            ['Executive', 'sales/dashboard'],
            ['Customers', 'sales/dashboard/customers'],
            ['Products', 'sales/dashboard/products'],
            ['Targets', 'sales/dashboard/targets'],
        ]],
        ['code' => 'sales_records', 'name' => 'Sales & Reports', 'icon' => 'chart-line', 'path' => '/sales/records', 'ribbons' => [
            ['Sales records', 'sales/records'],
            ['Reports', 'sales/reports'],
            ['Forecast', 'sales/forecast'],
            ['Insights', 'sales/insights'],
        ]],
        ['code' => 'sales_master_data', 'name' => 'Products & Customers', 'icon' => 'boxes', 'path' => '/sales/master/products', 'ribbons' => [
            ['Products', 'sales/master/products'],
            ['Customers', 'sales/master/customers'],
            ['Product classifications', 'sales/master/categories'],
            ['Regions & teams', 'sales/master/regions'],
        ]],
        ['code' => 'sales_targets', 'name' => 'Targets', 'icon' => 'bullseye', 'path' => '/sales/targets', 'ribbons' => [
            ['Targets', 'sales/targets'],
            ['Awaiting my approval', 'sales/targets/approvals'],
            ['Approval matrix', 'sales/targets/matrix'],
            ['Alert settings', 'sales/targets/alerts'],
        ]],
        ['code' => 'sales_data_centre', 'name' => 'Data Import Centre', 'icon' => 'file-import', 'path' => '/sales/imports', 'ribbons' => [
            ['Import centre', 'sales/imports'],
            ['File layouts', 'sales/layouts'],
            ['Mailbox setup', 'sales/mailboxes'],
        ]],
    ];

    public function run(): void
    {
        $now = now();
        $categoryId = DB::table('security_categories')->where('name', 'Sales Intelligence')->value('id')
            ?? DB::table('security_categories')->insertGetId([
                'name' => 'Sales Intelligence', 'icon' => 'chart-bar', 'description' => 'Vector Sales Intelligence',
                'active' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);

        $moduleIds = [];
        $ribbonIds = [];
        foreach (self::MODULES as $i => $m) {
            DB::table('security_modules')->updateOrInsert(['code_name' => $m['code']], [
                'category_id' => $categoryId, 'name' => $m['name'], 'path' => $m['path'],
                'active' => 1, 'font_awesome' => $m['icon'], 'updated_at' => $now,
            ]);
            $moduleId = DB::table('security_modules')->where('code_name', $m['code'])->value('id');
            DB::table('security_modules')->where('id', $moduleId)->whereNull('uuid')->update(['uuid' => (string) Str::uuid(), 'created_at' => $now]);
            $moduleIds[] = $moduleId;

            foreach ($m['ribbons'] as $sort => [$name, $path]) {
                DB::table('security_module_ribbons')->updateOrInsert(['module_id' => $moduleId, 'ribbon_path' => $path], [
                    'ribbon_name' => $name, 'sort_order' => $sort + 1, 'access_level' => 1, 'active' => 1, 'updated_at' => $now,
                ]);
                $rid = DB::table('security_module_ribbons')->where('module_id', $moduleId)->where('ribbon_path', $path)->value('id');
                DB::table('security_module_ribbons')->where('id', $rid)->whereNull('uuid')->update(['uuid' => (string) Str::uuid(), 'created_at' => $now]);
                $ribbonIds[] = $rid;
            }

            DB::table('billing_modules')->updateOrInsert(['code' => $m['code']], [
                'name' => 'Sales Intelligence · ' . $m['name'], 'category' => 'Sales Intelligence',
                'price_monthly' => 0, 'is_sellable' => false, 'is_active' => true, 'sort' => 100 + $i, 'updated_at' => $now,
            ]);
        }

        // Every current user may see the menu; the pages themselves enforce the §4.2 permissions.
        foreach (DB::table('users')->whereNull('deleted_at')->pluck('id') as $userId) {
            DB::table('security_category_access')->updateOrInsert(
                ['user_id' => $userId, 'category_id' => $categoryId],
                ['access_level' => 5, 'active' => 1, 'updated_at' => $now]
            );
            foreach ($moduleIds as $mid) {
                DB::table('security_module_access')->updateOrInsert(
                    ['user_id' => $userId, 'module_id' => $mid],
                    ['access_level' => 5, 'active' => 1, 'updated_at' => $now]
                );
            }
            foreach ($ribbonIds as $rid) {
                DB::table('security_ribbons_access')->updateOrInsert(
                    ['user_id' => $userId, 'ribbon_id' => $rid],
                    ['access_level' => 5, 'active' => 1, 'updated_at' => $now]
                );
            }
        }
    }
}
