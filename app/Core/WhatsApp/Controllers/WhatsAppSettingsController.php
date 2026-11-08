<?php

namespace App\Core\WhatsApp\Controllers;

use App\Core\Permission\Services\ModuleService;
use App\Core\WhatsApp\Models\WhatsAppConfiguration;
use App\Core\WhatsApp\WhatsAppConfig;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Structure → WhatsApp: the company's WhatsApp Business account, set once and used by
 * every module (CRM, Finance, HR, Projects, Documents, Fleet). CRM's own WhatsApp settings still
 * work for a company that has not filled this in; once this is complete it takes precedence.
 */
class WhatsAppSettingsController extends Controller
{
    public function index(): Response
    {
        $this->authorizeManage();
        $row = WhatsAppConfiguration::current();
        $resolved = WhatsAppConfig::resolve();

        return Inertia::render('Pages/Settings/WhatsApp', [
            'config' => [
                'display_number'       => $row?->display_number,
                'phone_number_id'      => $row?->phone_number_id,
                'business_account_id'  => $row?->business_account_id,
                'api_version'          => $row?->api_version ?? 'v21.0',
                'default_country_code' => $row?->default_country_code ?? '27',
                'is_active'            => $row?->is_active ?? true,
                'token_set'            => filled($row?->access_token),
                'token_hint'           => $row?->access_token ? '•••• ' . mb_substr($row->access_token, -4) : null,
                'last_tested_at'       => $row?->last_tested_at?->toDayDateTimeString(),
                'last_test_result'     => $row?->last_test_result,
            ],
            // What the system is actually using right now, and from where.
            'effective' => ['source' => $resolved->source, 'configured' => $resolved->isConfigured()],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeManage();
        $data = $request->validate([
            'display_number'       => ['nullable', 'string', 'max:30'],
            'phone_number_id'      => ['required', 'string', 'regex:/^\d{6,30}$/'],
            'business_account_id'  => ['nullable', 'string', 'regex:/^\d{6,30}$/'],
            'access_token'         => ['nullable', 'string', 'max:2000'],
            'api_version'          => ['required', 'string', 'regex:/^v\d{2}\.\d$/'],
            'default_country_code' => ['required', 'string', 'regex:/^\d{1,4}$/'],
            'is_active'            => ['boolean'],
        ], [
            'phone_number_id.regex' => 'The phone number ID is the long number Meta shows under WhatsApp → API Setup, not the phone number itself.',
        ]);

        $row = WhatsAppConfiguration::current() ?? new WhatsAppConfiguration;
        // A blank token field keeps the saved one: the page never sends the token back to the browser.
        if (blank($data['access_token'] ?? null)) {
            unset($data['access_token']);
            if (! $row->exists) {
                return back()->withErrors(['access_token' => 'Paste the access token from Meta to finish setting up WhatsApp.']);
            }
        }
        $row->fill($data + ['updated_by' => Auth::id()])->save();

        return back()->with('success', 'WhatsApp saved. Every module now sends from this account.');
    }

    /** Remove the company's account; modules fall back to CRM's settings or .env. */
    public function destroy(): RedirectResponse
    {
        $this->authorizeManage();
        WhatsAppConfiguration::current()?->delete();

        return back()->with('success', 'WhatsApp settings removed. Modules fall back to CRM’s WhatsApp settings, if any.');
    }

    /**
     * Send Meta's built-in "hello_world" template to a number. A template, because free text is only
     * delivered inside a 24-hour window the recipient opened — a test that fails for that reason
     * would say nothing about whether the credentials work.
     */
    public function test(Request $request): RedirectResponse
    {
        $this->authorizeManage();
        $data = $request->validate(['to' => ['required', 'string', 'max:30']]);
        $wa = WhatsAppConfig::resolve();
        if (! $wa->isConfigured()) {
            return back()->with('error', 'Save the phone number ID and access token first.');
        }

        try {
            $response = Http::withToken($wa->token)->timeout(20)->post($wa->messagesUrl(), [
                'messaging_product' => 'whatsapp', 'to' => $wa->normalise($data['to']), 'type' => 'template',
                'template' => ['name' => 'hello_world', 'language' => ['code' => 'en_US']],
            ]);
            $ok = $response->successful();
            $message = $ok ? 'Test message accepted by WhatsApp.' : ('WhatsApp refused it: ' . ($response->json('error.message') ?? 'HTTP ' . $response->status()));
        } catch (\Throwable $e) {
            $ok = false;
            $message = 'Could not reach WhatsApp: ' . $e->getMessage();
        }

        WhatsAppConfiguration::current()?->forceFill(['last_tested_at' => now(), 'last_test_result' => mb_substr($message, 0, 300)])->save();

        return back()->with($ok ? 'success' : 'error', $message);
    }

    /** Company administrators only: these are credentials that send messages in the company's name. */
    private function authorizeManage(): void
    {
        $user = Auth::user();
        if ($user?->isPlatformAdmin() || $user?->isMasterAdmin()) {
            return;
        }
        $moduleId = (int) DB::table('security_modules')->where('code_name', 'company_settings')->value('id');
        abort_unless($user && $moduleId && ModuleService::hasModuleAccess($user->id, $moduleId, 5), 403,
            'Only company administrators can change the WhatsApp account.');
    }
}
