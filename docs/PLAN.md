# Vector Sales Intelligence — build plan

Source: `docs/SRS.md` (SRS v1.0, 7 Nov 2026). Each phase is built, tested, committed, then stops.

## Already in place (from the hub carve-out)
Multi-tenant companies + Postgres RLS, login/MFA/sessions, configurable roles & permissions (Spatie),
audit log, platform admin (companies, plans, subscriptions, usage), AI service layer with metering/quota,
in-app + email notifications, queues/scheduler.

## Release 1 — core sales intelligence
| Phase | Scope | SRS |
|---|---|---|
| 1 | Foundation top-up: sales roles + permissions (§4.1/4.2), user data scopes (region/team/BU), Vector tenant + R35k/12-month subscription, second test tenant, sales nav (≤5 ribbons), cross-tenant test suite | ARC-001, IAM-001, SUB-001 |
| 2 | Master data: products, categories/subcategories, brands, units, product aliases; customers, groups, customer aliases; regions, business units, sales teams. CRUD, search, filters, export | MDM-001/002 |
| 3 | Data Import Centre: versioned templates, wizard (upload → map → validate → preview → confirm → queued commit), insert/update modes, error report, history. Reuse hub bulk-import code | IMP-001, DATA-001 |
| 4 | Sales records + historical import + reconciliation + controlled corrections; ingestion mode (transactions / incremental / cumulative snapshot / replacement) fixed per data source. **Needs the real Excel report** | SAL-001, HIS-001 |
| 5 | Email ingestion: Microsoft Graph (IMAP fallback), sender/subject/attachment rules, 03:30 SAST job, retries, file-hash idempotency, missing-report alert, integration dashboard | INT-001 |
| 6 | Executive / customer / product dashboards (ECharts, global filters, drill-down) + 20 standard reports + Excel/CSV/PDF export | BI-001, REP-001 |
| 7 | Basic targets: create, submit, approve (not by creator), actual vs target, achievement/variance/run-rate | TGT-001 (basic) |

## Release 2 — full target engine (allocation, revisions, formulas), custom report builder, forecasting, alerts
## Release 3 — AI assistant on structured queries, insights, anomalies, what-if, change-request workflow

## Blocking inputs (SRS §33)
Real daily Excel report (2–3 samples), mailbox + Microsoft 365 confirmation, sender/subject,
product & customer master files, historical sales files, target examples, financial-year start.

## Fresh install
1. As a Postgres superuser: `create database <db> owner <app_user>;` then in it
   `create extension pgcrypto; create extension vector; create extension ltree; create extension btree_gist; create extension postgis;`
2. `psql -f database/schema/pgsql-schema.sql` then `psql -f database/schema/reference-data.sql`
3. `php artisan migrate` (phase migrations) and `php artisan db:seed` (roles/permissions + platform admins)
4. `php artisan sales:provision-vector --admin-email=<client admin> --start=<contract start>` (`--with-test-tenant` adds a second company for isolation tests)

## Progress
- **Phase 1 done (2026-11-08):** spec roles + §4.2 permissions (`SalesAccessSeeder`, hub leftovers removed),
  `user_data_scopes` (RLS), contract fields on `subscriptions`, `sales:provision-vector`, platform tenancy
  commands/schedule restored, `tests/Feature/Sales/FoundationTest` (roles, contract, RLS, cross-tenant web access).
  Sales menu is added per phase as its screens arrive.
