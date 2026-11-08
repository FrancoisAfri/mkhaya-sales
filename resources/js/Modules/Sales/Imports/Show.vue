<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps<{
  batch: any
  previous: { uuid: string; file_name: string; completed_at: string } | null
  rows: { data: any[]; total: number; current_page: number; per_page: number }
  rowFilter: string
  search: string | null
  fields: Record<string, { label: string; type: string }>
  inspect: { headings?: string[]; error?: string } | null
  layouts: { id: number; name: string; version: number }[]
  modeLabels: Record<string, string>
  canCommit: boolean
}>()

const b = computed(() => props.batch)
const base = computed(() => `/sales/imports/${b.value.uuid}`)
const money = (n: any) => 'R ' + Number(n ?? 0).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const num = (n: any) => Number(n ?? 0).toLocaleString('en-ZA')
const editable = computed(() => ['staged', 'failed', 'uploaded'].includes(b.value.status))
const busy = computed(() => ['staging', 'committing'].includes(b.value.status))

const relayout = useForm({ layout_id: b.value.layout_id as number | null, mode: b.value.mode })
const totalsForm = useForm({ net_value: b.value.source_totals?.net_value ?? null, quantity: b.value.source_totals?.quantity ?? null, rows: b.value.source_totals?.rows ?? null })
const search = ref(props.search ?? '')

const mappedHeadings = computed(() => {
  const map = (b.value.layout?.mapping ?? []) as { source: string; field: string }[]
  return (props.inspect?.headings ?? []).map(h => {
    const m = map.find(x => x.source.trim().toLowerCase() === h.trim().toLowerCase())
    const f = m?.field ?? null
    return { heading: h, field: f ? (f.startsWith('attr:') ? `custom: ${f.slice(5)}` : props.fields[f]?.label ?? f) : (b.value.layout ? 'kept as custom field' : 'matched by heading') }
  })
})

const recon = computed(() => {
  const s = b.value.source_totals, t = b.value.totals
  if (!s || !t) return null
  return [
    { label: 'Net value', source: s.net_value, imported: t.net_value, fmt: money },
    { label: 'Quantity', source: s.quantity, imported: t.quantity, fmt: num },
    { label: 'Rows', source: s.rows, imported: b.value.valid_rows + b.value.warning_rows, fmt: num },
  ].filter(r => r.source !== null && r.source !== undefined && r.source !== '')
})

function go(extra: Record<string, any>) { router.get(base.value, { rows: props.rowFilter, q: search.value || undefined, ...extra }, { preserveState: true, preserveScroll: true }) }
function confirm() {
  if (window.confirm(`Commit ${b.value.valid_rows + b.value.warning_rows} rows? Invalid and duplicate rows are skipped.`)) router.post(`${base.value}/confirm`, {}, { preserveScroll: true })
}
const statusColor: Record<string, string> = { valid: 'success', warning: 'warning', invalid: 'error', duplicate: 'secondary' }
const headers = [{ title: 'Row', key: 'row_number', width: 70 }, { title: 'Status', key: 'status', width: 110 }, { title: 'Data', key: 'data' }, { title: 'Messages', key: 'messages' }]
const brief = (d: any) => Object.entries(d).filter(([k]) => !['attributes', 'customer_id', 'product_id'].includes(k)).map(([k, v]) => `${props.fields[k]?.label ?? k}: ${v}`).join(' · ')
</script>

<template>
  <VContainer fluid class="pa-4 pa-sm-6">
    <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
      <div>
        <VBtn variant="text" size="small" prepend-icon="tabler-arrow-left" href="/sales/imports" class="mb-1">Import centre</VBtn>
        <h3 class="text-h5 font-weight-bold mb-1">{{ b.file_name }}</h3>
        <p class="text-body-2 text-medium-emphasis mb-0">
          {{ b.target }} · {{ b.source }} · {{ b.layout ? `${b.layout.name} v${b.layout_version ?? b.layout.version}` : 'no layout (headings matched)' }}
          <span v-if="b.layout"> · {{ modeLabels[b.layout.ingestion_mode] }}</span>
        </p>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <VBtn variant="tonal" prepend-icon="tabler-file-download" :href="`${base}/file`">Original file</VBtn>
        <VBtn variant="tonal" prepend-icon="tabler-alert-triangle" :href="`${base}/errors`">Error report</VBtn>
        <VBtn v-if="canCommit && ['committed', 'failed', 'cancelled'].includes(b.status)" variant="tonal" prepend-icon="tabler-refresh" @click="router.post(`${base}/reprocess`)">Re-process</VBtn>
        <VBtn v-if="canCommit && editable" variant="text" color="error" @click="router.post(`${base}/cancel`)">Cancel import</VBtn>
        <VBtn v-if="canCommit && b.status === 'staged'" color="primary" prepend-icon="tabler-check" :disabled="b.valid_rows + b.warning_rows === 0" @click="confirm">Confirm import</VBtn>
      </div>
    </div>

    <VAlert v-if="busy" type="info" variant="tonal" class="mb-4">This file is being processed in the background. Refresh in a moment.
      <template #append><VBtn size="small" variant="text" @click="router.reload()">Refresh</VBtn></template></VAlert>
    <VAlert v-if="b.status === 'failed'" type="error" variant="tonal" class="mb-4">{{ b.error }}</VAlert>
    <VAlert v-if="previous" type="warning" variant="tonal" class="mb-4">
      This exact file was already imported ({{ previous.file_name }}). Rows already loaded are marked duplicate and will not be counted twice.
    </VAlert>
    <VAlert v-if="b.status === 'committed'" type="success" variant="tonal" class="mb-4">
      Committed: {{ num(b.inserted_rows) }} added, {{ num(b.updated_rows) }} updated<span v-if="b.replaced_rows">, {{ num(b.replaced_rows) }} earlier rows replaced</span>,
      {{ num(b.duplicate_rows) }} duplicates and {{ num(b.invalid_rows) }} invalid rows skipped.
    </VAlert>

    <VRow class="mb-2">
      <VCol v-for="k in [['Total rows', b.total_rows, 'default', 'all'], ['Valid', b.valid_rows, 'success', 'valid'], ['Warnings', b.warning_rows, 'warning', 'warning'], ['Invalid', b.invalid_rows, 'error', 'invalid'], ['Duplicates', b.duplicate_rows, 'secondary', 'duplicate']]" :key="k[0]" cols="6" md="">
        <VCard elevation="0" border rounded="lg" class="cursor-pointer" :class="{ 'border-primary': rowFilter === k[3] }" @click="go({ rows: k[3], page: 1 })">
          <VCardText class="py-3"><div class="text-caption text-medium-emphasis">{{ k[0] }}</div><div class="text-h5" :class="`text-${k[2]}`">{{ num(k[1]) }}</div></VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VRow v-if="b.target === 'sales' && b.totals">
      <VCol cols="12" md="7">
        <VCard elevation="0" border rounded="lg" title="Reconciliation">
          <VCardText>
            <VTable density="compact">
              <tbody>
                <tr><td>Net sales value (valid rows)</td><td class="text-end font-weight-bold">{{ money(b.totals.net_value) }}</td></tr>
                <tr><td>Gross sales value</td><td class="text-end">{{ money(b.totals.gross_value) }}</td></tr>
                <tr><td>Quantity</td><td class="text-end">{{ num(b.totals.quantity) }}</td></tr>
                <tr><td>Customers / products</td><td class="text-end">{{ num(b.totals.customers) }} / {{ num(b.totals.products) }}</td></tr>
                <tr><td>Dates in file</td><td class="text-end">{{ b.period_from?.slice(0, 10) }} → {{ b.period_to?.slice(0, 10) }}</td></tr>
              </tbody>
            </VTable>
            <VTable v-if="recon?.length" density="compact" class="mt-4">
              <thead><tr><th>Check</th><th class="text-end">Source says</th><th class="text-end">Imported</th><th class="text-end">Difference</th></tr></thead>
              <tbody><tr v-for="r in recon" :key="r.label">
                <td>{{ r.label }}</td><td class="text-end">{{ r.fmt(r.source) }}</td><td class="text-end">{{ r.fmt(r.imported) }}</td>
                <td class="text-end" :class="Math.abs(Number(r.source) - Number(r.imported)) > 0.009 ? 'text-error font-weight-bold' : 'text-success'">{{ r.fmt(Number(r.source) - Number(r.imported)) }}</td>
              </tr></tbody>
            </VTable>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="5">
        <VCard elevation="0" border rounded="lg" title="Source totals" subtitle="Enter the control totals from the sender to compare">
          <VCardText class="d-flex flex-column gap-3">
            <VTextField v-model="totalsForm.net_value" label="Net value" type="number" density="compact" variant="outlined" hide-details />
            <VTextField v-model="totalsForm.quantity" label="Quantity" type="number" density="compact" variant="outlined" hide-details />
            <VTextField v-model="totalsForm.rows" label="Row count" type="number" density="compact" variant="outlined" hide-details />
            <VBtn variant="tonal" :loading="totalsForm.processing" @click="totalsForm.post(`${base}/source-totals`, { preserveScroll: true })">Compare</VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard v-if="editable && canCommit" elevation="0" border rounded="lg" class="my-4" title="Column mapping">
      <VCardText>
        <VAlert v-if="inspect?.error" type="error" variant="tonal" class="mb-3">{{ inspect.error }}</VAlert>
        <div class="d-flex flex-wrap gap-2 mb-4">
          <VChip v-for="m in mappedHeadings" :key="m.heading" size="small" variant="tonal">{{ m.heading }} → {{ m.field }}</VChip>
        </div>
        <div class="d-flex flex-wrap gap-3 align-center">
          <VSelect v-model="relayout.layout_id" :items="layouts" :item-title="(l: any) => `${l.name} (v${l.version})`" item-value="id" clearable label="Use a different layout"
                   density="compact" variant="outlined" hide-details style="max-width: 360px" />
          <VSelect v-if="b.target !== 'sales'" v-model="relayout.mode" :items="['upsert', 'insert', 'update', 'reject']" label="Update rule" density="compact" variant="outlined" hide-details style="max-width: 200px" />
          <VBtn variant="tonal" :loading="relayout.processing" @click="relayout.put(base, { preserveScroll: true })">Validate again</VBtn>
          <VBtn variant="text" href="/sales/layouts">Build a layout</VBtn>
        </div>
      </VCardText>
    </VCard>

    <VCard elevation="0" border rounded="lg" title="Preview">
      <VCardText><VTextField v-model="search" density="compact" variant="outlined" placeholder="Search rows" prepend-inner-icon="tabler-search" hide-details style="max-width: 320px" @keyup.enter="go({ page: 1 })" /></VCardText>
      <VDataTableServer :headers="headers" :items="rows.data" :items-length="rows.total" :items-per-page="rows.per_page" :page="rows.current_page" density="compact"
                        @update:options="o => o.page !== rows.current_page && go({ page: o.page })">
        <template #item.status="{ item }"><VChip size="x-small" :color="statusColor[item.status]" variant="tonal">{{ item.status }}</VChip></template>
        <template #item.data="{ item }"><span class="text-caption">{{ brief(item.data) }}</span></template>
        <template #item.messages="{ item }"><span class="text-caption" :class="item.status === 'invalid' ? 'text-error' : ''">{{ (item.messages ?? []).join(' ') }}</span></template>
      </VDataTableServer>
    </VCard>
  </VContainer>
</template>
