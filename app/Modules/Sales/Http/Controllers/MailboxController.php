<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Jobs\FetchMailbox;
use App\Modules\Sales\Models\ImportBatch;
use App\Modules\Sales\Models\Mailbox;
use App\Modules\Sales\Models\MailboxRun;
use App\Modules\Sales\Models\ReportLayout;
use App\Modules\Sales\Services\Mail\MailConnector;
use App\Modules\Sales\Services\Mail\MailboxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Mailbox setup, health dashboard and processing log (SRS §5.1–5.5). */
class MailboxController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeSetup($request);
        $mailboxes = Mailbox::with('layout:id,name')->orderBy('name')->get()->map(function (Mailbox $m) {
            $runs = MailboxRun::where('mailbox_id', $m->id);
            $imports = ImportBatch::where('mailbox_id', $m->id);

            return $m->toArray() + [
                'has_credentials' => ! empty($m->credentials),
                'credential_keys' => array_keys($m->credentials ?? []),
                'next_run' => $m->nextRun()?->toIso8601String(),
                'stats' => [
                    'imported' => (clone $imports)->where('status', 'committed')->count(),
                    'failed' => (clone $imports)->where('status', 'failed')->count() + (clone $runs)->where('status', 'failed')->count(),
                    'awaiting' => (clone $imports)->where('status', 'staged')->count(),
                    'last_success' => (clone $imports)->where('status', 'committed')->max('completed_at'),
                ],
            ];
        });

        return Inertia::render('Modules/Sales/Mailboxes/Index', [
            'mailboxes' => $mailboxes,
            'runs' => MailboxRun::with('batch:id,uuid,status,inserted_rows,invalid_rows')->latest('started_at')->limit(50)->get(),
            'layouts' => ReportLayout::where('target', 'sales')->where('is_active', true)->get(['id', 'name']),
            'timezones' => ['Africa/Johannesburg', 'UTC', 'Europe/London', 'Africa/Nairobi', 'Africa/Lagos'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSetup($request);
        Mailbox::create($this->validated($request));

        return back()->with('success', 'Mailbox saved.');
    }

    public function update(Request $request, Mailbox $mailbox): RedirectResponse
    {
        $this->authorizeSetup($request);
        $data = $this->validated($request, $mailbox);
        $mailbox->update($data + ['status' => $data['enabled'] ? ($mailbox->status === 'disabled' ? 'new' : $mailbox->status) : 'disabled']);

        return back()->with('success', 'Mailbox saved.');
    }

    /** Connect and count matching emails without importing anything. */
    public function test(Request $request, Mailbox $mailbox, MailConnector $connector, MailboxService $service): RedirectResponse
    {
        $this->authorizeSetup($request);
        try {
            $messages = $connector->for($mailbox)->fetchSince(now()->subDays(max(1, $mailbox->lookback_days)), null);
            $matching = collect($messages)->filter(fn ($m) => $service->matches($mailbox, $m));
            $mailbox->update(['last_connected_at' => now(), 'status' => 'healthy', 'last_error' => null]);

            return back()->with('success', "Connected. {$matching->count()} of " . count($messages) . ' recent emails match the rules.');
        } catch (\Throwable $e) {
            $mailbox->update(['status' => 'failing', 'last_error' => $e->getMessage()]);

            return back()->withErrors(['mailbox' => 'Connection failed: ' . $e->getMessage()]);
        }
    }

    /** "Run now" — e.g. after fixing a rule, or when the report arrived late. */
    public function run(Request $request, Mailbox $mailbox): RedirectResponse
    {
        $this->authorizeSetup($request);
        FetchMailbox::dispatchSync($mailbox->id, 'manual', $request->user()->id);

        return back()->with('success', 'Mailbox checked — see the processing log.');
    }

    private function validated(Request $request, ?Mailbox $existing = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'provider' => ['required', Rule::in(['graph', 'imap'])],
            'mailbox_address' => ['required', 'string', 'max:190'],
            'folder' => ['nullable', 'string', 'max:120'],
            'sender' => ['nullable', 'string', 'max:190'],
            'subject_pattern' => ['nullable', 'string', 'max:190'],
            'filename_pattern' => ['nullable', 'string', 'max:190'],
            'extensions' => ['nullable', 'string', 'max:60'],
            'layout_id' => ['nullable', 'integer', Rule::exists('report_layouts', 'id')->where('company_id', $request->user()->company_id)],
            'run_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'timezone' => ['required', 'timezone'],
            'run_days' => ['nullable', 'array'],
            'run_days.*' => ['integer', 'between:1,7'],
            'lookback_days' => ['required', 'integer', 'between:1,31'],
            'missing_after_minutes' => ['required', 'integer', 'between:15,1440'],
            'auto_commit' => ['boolean'],
            'enabled' => ['boolean'],
            // Secrets: blank means "keep what is stored".
            'credentials' => ['nullable', 'array'],
            'credentials.tenant_id' => ['nullable', 'string', 'max:100'],
            'credentials.client_id' => ['nullable', 'string', 'max:100'],
            'credentials.client_secret' => ['nullable', 'string', 'max:500'],
            'credentials.host' => ['nullable', 'string', 'max:190'],
            'credentials.port' => ['nullable', 'integer'],
            'credentials.encryption' => ['nullable', Rule::in(['ssl', 'tls', 'none'])],
            'credentials.password' => ['nullable', 'string', 'max:500'],
        ]);

        $creds = array_filter($data['credentials'] ?? [], fn ($v) => $v !== null && $v !== '');
        $data['credentials'] = array_merge($existing?->credentials ?? [], $creds);
        $data['folder'] = $data['folder'] ?: ($data['provider'] === 'imap' ? 'INBOX' : 'inbox');
        $data['run_days'] = empty($data['run_days']) ? null : array_values(array_map('intval', $data['run_days']));
        // A changed time means today's run is decided by the new time.
        if ($existing && $existing->run_time !== $data['run_time']) {
            $data['last_scheduled_on'] = null;
        }

        return $data;
    }

    private function authorizeSetup(Request $request): void
    {
        abort_unless($request->user()->can('integrations.manage'), 403);
    }
}
