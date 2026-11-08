<?php

use App\Support\Database\TenantRls;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phases 3–4 — Data Import Centre, generic file layouts, the sales fact table (SRS IMP-001,
 * DATA-001, SAL-001, HIS-001, §21.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        // A client's own file format: which sheet, which header row, which column means what.
        // Nothing about a particular client's report is in code — a new report is a new layout.
        Schema::create('report_layouts', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('name', 150);
            $t->string('target', 20);                       // sales | products | customers
            $t->string('sheet', 120)->nullable();           // sheet name; null = first sheet
            $t->unsignedSmallInteger('header_row')->default(1);
            $t->string('date_format', 30)->nullable();      // e.g. d/m/Y; null = auto
            $t->boolean('decimal_comma')->default(false);
            $t->string('ingestion_mode', 20)->default('append'); // append | upsert_daily | replace_period
            $t->string('unknown_masters', 10)->default('create'); // create (flag for review) | reject
            $t->jsonb('mapping');                           // [{source, field}]
            $t->boolean('keep_unmapped')->default(true);    // extra columns kept in `attributes`
            $t->jsonb('dedupe_fields')->nullable();         // fields that identify a row
            $t->unsignedInteger('version')->default(1);
            $t->boolean('is_active')->default(true);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'name']);
        });
        TenantRls::enable('report_layouts');

        Schema::create('import_batches', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->string('target', 20);
            $t->string('source', 20)->default('upload');    // upload | historical | email
            $t->foreignId('layout_id')->nullable()->constrained('report_layouts')->nullOnDelete();
            $t->unsignedInteger('layout_version')->nullable();
            $t->string('mode', 20)->default('upsert');      // masters: insert | update | upsert | reject
            $t->string('file_name', 255);
            $t->string('file_path', 255)->nullable();       // on the documents (database) disk
            $t->string('file_hash', 64);
            $t->string('status', 20)->default('uploaded');  // uploaded|staging|staged|committing|committed|failed|cancelled
            $t->unsignedInteger('total_rows')->default(0);
            $t->unsignedInteger('valid_rows')->default(0);
            $t->unsignedInteger('invalid_rows')->default(0);
            $t->unsignedInteger('duplicate_rows')->default(0);
            $t->unsignedInteger('warning_rows')->default(0);
            $t->unsignedInteger('inserted_rows')->default(0);
            $t->unsignedInteger('updated_rows')->default(0);
            $t->unsignedInteger('replaced_rows')->default(0);
            $t->date('period_from')->nullable();
            $t->date('period_to')->nullable();
            $t->jsonb('totals')->nullable();                // value, quantity, customers, products
            $t->jsonb('source_totals')->nullable();         // what the sender says the file holds
            $t->jsonb('headers')->nullable();
            $t->text('error')->nullable();
            $t->foreignId('previous_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $t->unsignedBigInteger('mailbox_id')->nullable();
            $t->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'target', 'created_at']);
            $t->index(['company_id', 'file_hash']);
        });
        TenantRls::enable('import_batches');

        Schema::create('import_rows', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('batch_id')->constrained('import_batches')->cascadeOnDelete();
            $t->unsignedInteger('row_number');
            $t->string('status', 12);                       // valid | warning | invalid | duplicate
            $t->jsonb('data');
            $t->jsonb('messages')->nullable();
            $t->string('row_key', 64)->nullable();
            $t->index(['batch_id', 'status', 'row_number']);
        });
        TenantRls::enable('import_rows');

        Schema::create('sales_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $t->foreignId('layout_id')->nullable()->constrained('report_layouts')->nullOnDelete();
            $t->date('transaction_date');
            $t->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $t->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $t->decimal('quantity', 18, 4)->default(0);
            $t->string('unit_of_measure', 30)->nullable();
            $t->decimal('unit_price', 18, 4)->nullable();
            $t->decimal('gross_value', 18, 2)->default(0);
            $t->decimal('discount', 18, 2)->default(0);
            $t->decimal('net_value', 18, 2)->default(0);
            $t->string('currency', 3)->default('ZAR');
            $t->foreignId('region_id')->nullable()->constrained('regions')->nullOnDelete();
            $t->foreignId('business_unit_id')->nullable()->constrained('business_units')->nullOnDelete();
            $t->foreignId('sales_team_id')->nullable()->constrained('sales_teams')->nullOnDelete();
            $t->foreignId('salesperson_id')->nullable()->constrained('salespeople')->nullOnDelete();
            $t->foreignId('distribution_centre_id')->nullable()->constrained('distribution_centres')->nullOnDelete();
            $t->string('external_id', 120)->nullable();
            $t->string('row_key', 64);                      // identity of the source row
            $t->string('status', 12)->default('active');    // active | replaced
            $t->unsignedInteger('source_row')->nullable();
            $t->jsonb('attributes')->nullable();            // client-specific extra columns
            $t->boolean('is_adjusted')->default(false);
            $t->timestamps();
            $t->index(['company_id', 'status', 'transaction_date']);
            $t->index(['company_id', 'customer_id', 'transaction_date']);
            $t->index(['company_id', 'product_id', 'transaction_date']);
            $t->index(['company_id', 'layout_id', 'transaction_date']);
        });
        // A source row exists once among the live records — the database refuses a double count.
        DB::statement("CREATE UNIQUE INDEX sales_records_live_row_key ON sales_records (company_id, row_key) WHERE status = 'active'");
        TenantRls::enable('sales_records');

        // Controlled corrections: the imported value is never overwritten without a trace (§12.3).
        Schema::create('sales_adjustments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('sales_record_id')->constrained('sales_records')->cascadeOnDelete();
            $t->string('field', 40);
            $t->string('original_value', 120)->nullable();
            $t->string('new_value', 120)->nullable();
            $t->text('reason');
            $t->foreignId('requested_by')->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 12)->default('pending');   // pending | applied | rejected
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'status']);
        });
        TenantRls::enable('sales_adjustments');
    }

    public function down(): void
    {
        foreach (['sales_adjustments', 'sales_records', 'import_rows', 'import_batches', 'report_layouts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
