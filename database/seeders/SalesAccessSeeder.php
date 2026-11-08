<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles and permissions for Vector Sales Intelligence (SRS IAM-001 §4.1–4.2).
 *
 * Roles are a starting set, not hard-coded behaviour: administrators change what each role may do
 * in Roles & Permissions, and this seeder only ADDS missing grants — it never takes away one an
 * administrator gave. The SaaS super administrator is the users.is_platform_admin flag, not a role.
 *
 * Idempotent. Also removes the hub-era roles and permissions nobody holds.
 */
class SalesAccessSeeder extends Seeder
{
    /** §4.2 — one permission per controllable action. */
    public const PERMISSIONS = [
        'dashboards.view'      => 'View dashboards',
        'sales.view'           => 'View sales records',
        'customers.view'       => 'View customer information',
        'products.view'        => 'View product information',
        'products.manage'      => 'Add and edit products',
        'customers.manage'     => 'Add and edit customers',
        'imports.products'     => 'Import products',
        'imports.customers'    => 'Import customers',
        'imports.historical'   => 'Import historical sales',
        'targets.create'       => 'Create targets',
        'targets.edit'         => 'Edit targets',
        'targets.submit'       => 'Submit targets',
        'targets.approve'      => 'Approve targets',
        'targets.revise'       => 'Revise targets',
        'reports.export'       => 'Export reports',
        'users.manage'         => 'Manage users',
        'integrations.manage'  => 'Manage integrations',
        'audit.view'           => 'View audit logs',
        'settings.manage'      => 'Manage organisation settings',
        'ai.use'               => 'Use the AI assistant',
    ];

    /** Generic CRUD permissions the platform's own screens check (crud.permissions middleware). */
    private const CORE = ['view', 'create', 'update', 'modify', 'delete'];

    /** §4.1 starting roles → permissions. */
    public const ROLES = [
        'organisation administrator' => '*',
        'executive' => ['dashboards.view', 'sales.view', 'customers.view', 'products.view', 'reports.export', 'ai.use', 'view'],
        'sales manager' => [
            'dashboards.view', 'sales.view', 'customers.view', 'products.view',
            'targets.create', 'targets.edit', 'targets.submit', 'targets.approve', 'targets.revise',
            'reports.export', 'ai.use', 'view',
        ],
        'sales analyst' => ['dashboards.view', 'sales.view', 'customers.view', 'products.view', 'reports.export', 'ai.use', 'view'],
        'data administrator' => [
            'dashboards.view', 'sales.view', 'customers.view', 'products.view',
            'products.manage', 'customers.manage', 'imports.products', 'imports.customers', 'imports.historical',
            'view', 'create', 'update', 'modify',
        ],
        'sales user' => ['dashboards.view', 'sales.view', 'customers.view', 'products.view', 'view'],
        'read-only user' => ['dashboards.view', 'sales.view', 'customers.view', 'products.view', 'view'],
    ];

    /** Platform roles the application itself refers to by name. */
    private const PLATFORM_ROLES = ['administrator', 'super admin', 'user', 'manager', 'reseller'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $all = array_merge(array_keys(self::PERMISSIONS), self::CORE);
        foreach ($all as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        foreach (self::ROLES as $roleName => $grants) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->givePermissionTo($grants === '*' ? $all : $grants);
        }

        // The system administrator keeps everything.
        Role::firstOrCreate(['name' => 'administrator', 'guard_name' => 'web'])->givePermissionTo($all);
        foreach (self::PLATFORM_ROLES as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $this->removeHubLeftovers($all);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Hub-era roles and permissions (WMS, payroll, board…) that no user holds. */
    private function removeHubLeftovers(array $keepPermissions): void
    {
        $keepRoles = array_merge(array_keys(self::ROLES), self::PLATFORM_ROLES);

        $heldRoleIds = DB::table('model_has_roles')->pluck('role_id')->unique();
        Role::query()
            ->whereNotIn('name', $keepRoles)
            ->whereNotIn('id', $heldRoleIds)
            ->get()
            ->each(fn (Role $r) => $r->delete());

        $heldPermIds = DB::table('model_has_permissions')->pluck('permission_id')->unique();
        Permission::query()
            ->whereNotIn('name', $keepPermissions)
            ->whereNotIn('id', $heldPermIds)
            ->delete();
    }
}
