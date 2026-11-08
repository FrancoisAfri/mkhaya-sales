// A stored code (qty, oee, APPROVE_BOM, eng_waste_hotspots) as words for a screen, when no
// translation exists for it: "Quantity", "OEE", "Approve BOM", "Engineering waste hotspots".
// Anything that is not a code (a sentence, a number, a reference like PO-2026-00008) is left as it is.
const WORDS: Record<string, string> = {
  qty: 'quantity', pct: '%', avg: 'average', uom: 'unit', ref: 'reference', eng: 'engineering',
  mfg: 'manufacturing', num: 'number', min: 'minimum', max: 'maximum', reman: 'remanufacturing',
}
const ACRONYMS = new Set(['oee', 'ai', 'bom', 'boms', 'eco', 'kpi', 'sku', 'po', 'so', 'grn', 'id', 'ppm', 'mtbf', 'mttr', 'co2', 'vat', 'gl', 'wip', 'fg', 'rm', 'qc', 'iot', 'plc', 'mes', 'erp', 'csv', 'api', 'sla', 'ewma', 'spc', 'fifo', 'fefo', 'lifo', 'mrp', 'eto', 'mto', 'mts', 'abc', 'sscc', 'gs1', 'edi', 'asn', 'pod', 'rfq', 'ncr', 'capa', 'scada', 'mqtt', 'http', 'opc', 'ua'])

const isCode = (s: string) =>
  /^[A-Za-z][A-Za-z0-9]*([_.][A-Za-z0-9]+)*$/.test(s) && (/[_.]/.test(s) || s === s.toUpperCase() || s === s.toLowerCase())

export function humanise(code: unknown): string {
  if (code === null || code === undefined || code === '') return '—'
  const s = String(code)
  if (!isCode(s)) return s
  const words = s.replace(/^is_/i, '').replace(/_id$/i, '').split(/[_.]+/).filter(Boolean)
    .map(w => w.toLowerCase())
    .map(w => (ACRONYMS.has(w) ? w.toUpperCase() : WORDS[w] ?? w))
  const text = words.join(' ')
  return text.charAt(0).toUpperCase() + text.slice(1)
}
