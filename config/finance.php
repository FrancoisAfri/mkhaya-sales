<?php

return [
    /*
    | The functional/base currency the ledger reports in. Foreign-currency
    | postings are converted to this via fin_exchange_rates. ZAR for SA SMEs.
    */
    'base_currency' => env('FINANCE_BASE_CURRENCY', 'ZAR'),

    /*
    | Account code that the year-end run posts net profit/(loss) into when it
    | closes the P&L. Must be a postable equity account in the chart.
    */
    'retained_earnings_account' => env('FINANCE_RETAINED_EARNINGS', '3100'),

    /*
    | Control accounts the AR/AP engine posts against. All must be postable.
    */
    'ar_control'  => env('FINANCE_AR_CONTROL', '1200'),  // Accounts Receivable
    'ap_control'  => env('FINANCE_AP_CONTROL', '2100'),  // Accounts Payable
    // Goods Received Not Invoiced: credited when a GRN posts stock, debited when the supplier's
    // bill for those goods is captured, so a delivery never owes the supplier twice.
    'grni_account' => env('FINANCE_GRNI_ACCOUNT', '2150'),
    'vat_output'  => env('FINANCE_VAT_OUTPUT', '2200'),  // VAT on sales (liability)
    'vat_input'   => env('FINANCE_VAT_INPUT', '1400'),   // VAT on purchases (asset)
    'default_bank' => env('FINANCE_DEFAULT_BANK', '1100'),
    'bad_debt_account' => env('FINANCE_BAD_DEBT', '6800'),   // expense account for write-offs
    'retention_account' => env('FINANCE_RETENTION', '1250'), // retention receivable (held back on progress claims)

    /*
    | Fixed-asset defaults (Phase 5.1). Each asset can override these; these are
    | the fallbacks the depreciation and disposal engine posts against.
    */
    'fixed_asset_account'          => env('FINANCE_FIXED_ASSET', '1510'), // cost account
    'accum_depreciation_account'   => env('FINANCE_ACCUM_DEP', '1520'),   // contra-asset
    'depreciation_expense_account' => env('FINANCE_DEP_EXPENSE', '6500'), // P&L expense
    'asset_disposal_account'       => env('FINANCE_DISPOSAL', '4900'),    // gain/loss on disposal

    // Inventory (Phase 5.2)
    'inventory_account' => env('FINANCE_INVENTORY', '1300'),
    'cogs_account'      => env('FINANCE_COGS', '5000'),
    // Standard VAT rate for fees Mkhaya invoices (Accountant Partner); a setting, not code.
    'vat_rate'          => (float) env('FINANCE_VAT_RATE', 15),

    // Accountant Partner Programme: null = on for the SaaS edition, off when self-hosted.
    // Statement files are made after the response unless a queue worker is running (STATEMENTS_QUEUE_WORKER=true).
    // Arelle, to validate iXBRL before filing (e.g. "arelleCmdLine" or "python3 -m arelle.CntlrCmdLine"). Empty: not validated.
    'arelle_command' => env('FINANCE_ARELLE_COMMAND', ''),

    'statements_queue_worker' => (bool) env('STATEMENTS_QUEUE_WORKER', false),

    // SARS Connect, assisted eFiling sessions: where the Mkhaya extension opens eFiling, and -- on a
    // developer's machine only, never in production -- a local page that stands in for eFiling.
    /*
     * CIPC annual return fees by annual turnover (rand), with the fee once the 30-business-day
     * window has passed. As published by CIPC; check its fee schedule before filing.
     */
    'cipc_annual_return_fees' => [
        'company' => [
            ['label' => 'Turnover under R1 million', 'below' => 1_000_000, 'fee' => 100, 'late' => 150],
            ['label' => 'R1 million to under R10 million', 'below' => 10_000_000, 'fee' => 450, 'late' => 500],
            ['label' => 'R10 million to under R25 million', 'below' => 25_000_000, 'fee' => 2000, 'late' => 2500],
            ['label' => 'R25 million and more', 'below' => null, 'fee' => 3000, 'late' => 4000],
        ],
        'close_corporation' => [
            ['label' => 'Turnover under R1 million', 'below' => 1_000_000, 'fee' => 100, 'late' => 150],
            ['label' => 'R1 million to under R10 million', 'below' => 10_000_000, 'fee' => 450, 'late' => 500],
            ['label' => 'R10 million to under R25 million', 'below' => 25_000_000, 'fee' => 2000, 'late' => 2500],
            ['label' => 'R25 million and more', 'below' => null, 'fee' => 3000, 'late' => 4000],
        ],
        'npc' => [
            ['label' => 'Non-profit company (any turnover)', 'below' => null, 'fee' => 100, 'late' => 150],
        ],
    ],
    'cipc_fee_note' => 'Fees as published by CIPC. Confirm on the CIPC fee schedule before paying.',
    'cipc_filing_url' => env('CIPC_FILING_URL', 'https://eservices.cipc.co.za'),

    'sars_efiling_url' => env('SARS_EFILING_URL', 'https://secure.sarsefiling.co.za'),
    'sars_efiling_mock_url' => env('SARS_EFILING_MOCK') ? rtrim((string) env('APP_URL'), '/').'/api/dev/sars-efiling-mock' : null,
    // The environment SARS Connect's channels run in: test or production (default: production only in production).
    'sars_environment' => env('SARS_ENVIRONMENT'),

    'partner_programme' => env('ACCOUNTANT_PARTNER_PROGRAMME') === null ? null : filter_var(env('ACCOUNTANT_PARTNER_PROGRAMME'), FILTER_VALIDATE_BOOL),

    // Manufacturing. Material issued to a production order is work in progress, not a cost of
    // sale; it becomes finished goods when the order completes. Variance and scrap carry the
    // difference between what an order should have cost and what it did.
    'wip_account'                 => env('FINANCE_WIP', '1310'),
    'production_variance_account' => env('FINANCE_PRODUCTION_VARIANCE', '5600'),
    'scrap_account'               => env('FINANCE_SCRAP', '5610'),

    // Expenses (5.5) & Staff loans (5.6)
    'mileage_rate'           => env('FINANCE_MILEAGE_RATE', 4.84),
    'staff_loan_receivable'  => env('FINANCE_STAFF_LOAN', '1270'),
];
