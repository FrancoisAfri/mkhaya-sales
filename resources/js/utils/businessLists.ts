/**
 * Fixed lists for business records (a client company, a practice, its staff), so the same
 * thing is always spelled the same way and reports, filters and the finance intelligence
 * templates can rely on it. Values are stored as the text shown, so existing free-text
 * values stay valid, and every *Items() helper keeps a value already on record that is not
 * in the list, so editing an old record never blanks it.
 */
const withCurrent = (list: string[], current?: string | null): string[] =>
  current && !list.includes(current) ? [current, ...list] : list

/**
 * Industries. The CRM's list (AccountController::INDUSTRIES) plus the trades South African
 * practices most often keep books for. Finance Intelligence picks a starting template from
 * this text (Industries::guess), so the names keep the words it looks for.
 */
export const INDUSTRIES: string[] = [
  'Agriculture & Farming', 'Automotive', 'Banking', 'Beauty & Wellness', 'Construction', 'Consulting', 'Education',
  'Electrical & Plumbing Contractors', 'Energy & Utilities', 'Engineering', 'Financial Services', 'Food & Beverage',
  'Government', 'Healthcare & Medical', 'Hospitality & Restaurants', 'Information Technology', 'Insurance', 'Legal',
  'Logistics & Transport', 'Manufacturing', 'Media & Entertainment', 'Mining', 'Non-Profit Organisation',
  'Pharmaceuticals', 'Professional Services', 'Property Management', 'Real Estate', 'Retail', 'Security Services',
  'Telecommunications', 'Tourism & Travel', 'Wholesale & Distribution', 'Other',
]
export const industryItems = (current?: string | null) => withCurrent(INDUSTRIES, current)

/** What a practice does for a client each month — the engagement's service package. */
export const SERVICE_PACKAGES: string[] = [
  'Monthly bookkeeping & VAT',
  'Monthly bookkeeping, VAT & payroll',
  'Full service: bookkeeping, VAT, payroll, AFS & tax',
  'Bookkeeping only',
  'VAT returns only',
  'Payroll only',
  'Management accounts',
  'Annual financial statements & income tax',
  'Income tax & provisional tax only',
  'Company secretarial (CIPC)',
  'Once-off / ad hoc work',
]
export const servicePackageItems = (current?: string | null) => withCurrent(SERVICE_PACKAGES, current)

/** SARS-recognised controlling bodies a tax practitioner can belong to. */
export const RECOGNISED_BODIES: string[] = [
  'SAICA – South African Institute of Chartered Accountants',
  'SAIPA – South African Institute of Professional Accountants',
  'SAIT – South African Institute of Tax Professionals',
  'CIBA – Chartered Institute for Business Accountants',
  'ICB – Institute of Certified Bookkeepers',
  'IAC – Institute of Accounting and Commerce',
  'ACCA – Association of Chartered Certified Accountants',
  'CIMA – Chartered Institute of Management Accountants',
  'CGISA – Chartered Governance Institute of Southern Africa',
  'FPI – Financial Planning Institute of Southern Africa',
  'ACFE – Association of Certified Fraud Examiners (SA)',
  'LPC – Legal Practice Council',
  'Other',
]
export const recognisedBodyItems = (current?: string | null) => withCurrent(RECOGNISED_BODIES, current)

/** South African banks with their universal branch codes (filled in when a bank is picked). */
export const SA_BANKS: { name: string; branch: string }[] = [
  { name: 'Absa Bank', branch: '632005' },
  { name: 'African Bank', branch: '430000' },
  { name: 'Bidvest Bank', branch: '462005' },
  { name: 'Capitec Bank', branch: '470010' },
  { name: 'Discovery Bank', branch: '679000' },
  { name: 'First National Bank (FNB)', branch: '250655' },
  { name: 'Investec Bank', branch: '580105' },
  { name: 'Nedbank', branch: '198765' },
  { name: 'Postbank', branch: '460005' },
  { name: 'Sasfin Bank', branch: '683000' },
  { name: 'Standard Bank', branch: '051001' },
  { name: 'TymeBank', branch: '678910' },
]
export const bankItems = (current?: string | null) => withCurrent(SA_BANKS.map(b => b.name), current)
/** The universal branch code for a bank picked from the list, or null for one typed in earlier. */
export const bankBranch = (name?: string | null): string | null => SA_BANKS.find(b => b.name === name)?.branch ?? null

/** Reporting currencies: the rand and the region first, then the majors. */
export const CURRENCIES: { value: string; title: string }[] = [
  ['ZAR', 'South African rand'], ['BWP', 'Botswana pula'], ['NAD', 'Namibian dollar'], ['LSL', 'Lesotho loti'],
  ['SZL', 'Eswatini lilangeni'], ['MZN', 'Mozambican metical'], ['ZMW', 'Zambian kwacha'], ['ZWG', 'Zimbabwe gold'],
  ['MWK', 'Malawian kwacha'], ['KES', 'Kenyan shilling'], ['NGN', 'Nigerian naira'], ['GHS', 'Ghanaian cedi'],
  ['USD', 'US dollar'], ['EUR', 'Euro'], ['GBP', 'British pound'], ['AUD', 'Australian dollar'], ['CNY', 'Chinese yuan'],
  ['AED', 'UAE dirham'], ['INR', 'Indian rupee'],
].map(([value, name]) => ({ value, title: `${value} – ${name}` }))
export const currencyItems = (current?: string | null) =>
  current && !CURRENCIES.some(c => c.value === current) ? [{ value: current, title: current }, ...CURRENCIES] : CURRENCIES
