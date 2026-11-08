<?php

use App\Support\Database\TenantRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 5 — authorised reporting mailbox and its processing log (SRS INT-001, §21.5). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailboxes', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 150);
            $t->string('provider', 10);                    // graph | imap
            $t->text('credentials')->nullable();           // encrypted JSON — never sent to the browser
            $t->string('mailbox_address', 190)->nullable();// the one mailbox (Graph) / login (IMAP)
            $t->string('folder', 120)->default('inbox');
            // Which email is the report (§5.2) — anything else is never downloaded.
            $t->string('sender', 190)->nullable();         // address or @domain
            $t->string('subject_pattern', 190)->nullable();// text it contains, or /regex/
            $t->string('filename_pattern', 190)->nullable();// e.g. Daily*Sales*.xlsx
            $t->string('extensions', 60)->default('xlsx,xls,csv');
            $t->foreignId('layout_id')->nullable()->constrained('report_layouts')->nullOnDelete();
            // When (§5.3) — configurable per mailbox, in its own time zone.
            $t->string('run_time', 5)->default('03:30');
            $t->string('timezone', 60)->default('Africa/Johannesburg');
            $t->jsonb('run_days')->nullable();             // ISO weekdays 1–7; null = every day
            $t->unsignedSmallInteger('lookback_days')->default(2);
            $t->unsignedSmallInteger('missing_after_minutes')->default(120);
            $t->boolean('auto_commit')->default(true);     // clean files commit themselves
            $t->boolean('enabled')->default(true);
            $t->date('last_scheduled_on')->nullable();
            $t->timestamp('last_run_at')->nullable();
            $t->timestamp('last_connected_at')->nullable();
            $t->timestamp('last_email_at')->nullable();
            $t->timestamp('last_import_at')->nullable();
            $t->timestamp('token_expires_at')->nullable();
            $t->string('status', 20)->default('new');      // new | healthy | failing | disabled
            $t->text('last_error')->nullable();
            $t->timestamps();
        });
        TenantRls::enable('mailboxes');

        Schema::create('mailbox_runs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('mailbox_id')->constrained('mailboxes')->cascadeOnDelete();
            $t->string('trigger', 12);                     // schedule | manual | retry
            $t->string('status', 16)->default('running');  // running | imported | no_email | duplicate | failed
            $t->unsignedTinyInteger('attempt')->default(1);
            $t->string('message_id', 255)->nullable();
            $t->string('subject', 255)->nullable();
            $t->string('sender', 190)->nullable();
            $t->timestamp('received_at')->nullable();
            $t->string('attachment_name', 255)->nullable();
            $t->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $t->unsignedInteger('emails_seen')->default(0);
            $t->text('error')->nullable();
            $t->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('started_at');
            $t->timestamp('finished_at')->nullable();
            $t->index(['mailbox_id', 'started_at']);
            $t->index(['mailbox_id', 'message_id']);
        });
        TenantRls::enable('mailbox_runs');
    }

    public function down(): void
    {
        Schema::dropIfExists('mailbox_runs');
        Schema::dropIfExists('mailboxes');
    }
};
