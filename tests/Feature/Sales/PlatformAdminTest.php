<?php

namespace Tests\Feature\Sales;

use App\Core\User\Models\User;
use Illuminate\Support\Str;

class PlatformAdminTest extends SalesTestCase
{
    public function test_a_platform_admin_without_a_company_is_sent_to_choose_one(): void
    {
        $admin = User::create(['uuid' => (string) Str::uuid(), 'email' => 'pa-' . Str::random(6) . '@test.local', 'password' => bcrypt('x'),
            'status' => 1, 'is_platform_admin' => true, 'password_changed_at' => now()]);
        $admin->assignRole('administrator');

        foreach (['/', '/sales/targets/alerts', '/sales/dashboard', '/sales/imports', '/sales/master/products'] as $url) {
            $this->actingAs($admin)->get($url)->assertRedirect('/platform/companies');
        }
    }
}
