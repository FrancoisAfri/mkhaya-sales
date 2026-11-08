<?php

namespace App\Support\Hubs;

use App\Support\Hubs\Modules\CompanySettingsHubs;
use App\Support\Hubs\Modules\ReportsHubs;
use App\Support\Hubs\Modules\RolesPermissionsHubs;
use App\Support\Hubs\Modules\SystemSettingsHubs;
use App\Support\Hubs\Modules\UserManagementHubs;

/**
 * Maps a module code_name to its ModuleHubs config class, so the generic middleware/controller
 * can serve any hubbed module without per-module wiring. (Content Marketing predates this and
 * keeps its own dedicated controller/middleware — it is intentionally not registered here.)
 */
class HubRegistry
{
    /** @var array<string, class-string<ModuleHubs>> */
    private const MAP = [
        'system_settings'       => SystemSettingsHubs::class,
        'reports'               => ReportsHubs::class,
        'user_management'       => UserManagementHubs::class,
        'company_settings'      => CompanySettingsHubs::class,
        'roles_permissions'     => RolesPermissionsHubs::class,
    ];

    /**
     * Modules whose hub landing routes are registered generically (routes/modules/hubs.php).
     * The first-wave modules declare theirs inside their own route files.
     *
     * @var list<string>
     */
    public const GENERIC_HUB_ROUTES = [
        'system_settings', 'reports', 'user_management', 'company_settings', 'roles_permissions',
    ];

    /** @return class-string<ModuleHubs>|null */
    public static function for(string $code): ?string
    {
        return self::MAP[$code] ?? null;
    }

    /** @return array<string, class-string<ModuleHubs>> code_name => hubs class */
    public static function all(): array
    {
        return self::MAP;
    }
}
