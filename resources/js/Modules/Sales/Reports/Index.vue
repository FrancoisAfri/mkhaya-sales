<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'
import FilterBar from '@/Components/Sales/FilterBar.vue'

const props = defineProps<{
  catalogue: { key: string; title: string }[]; saved: any[]; report: any; result: any
  dimensions: Record<string, string>; measures: Record<string, string>; filters: Record<string, any>; options: Record<string, any[]>; canExport: boolean
}>()

const money = (n: any) => Number(n ?? 0).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const pct = (n: any) => n === null || n === undefined ? '—' : `${n > 0 ? '+' : ''}${n}%`
const builder = reactive({ dims: props.report.dims ?? ['customer'], measure: props.report.measure ?? 'net_value', compare: props.report.compare ?? 'previous_year', order: props.report.order ?? 'desc' })
const saveForm = useForm({ name: '', shared: false, definition: {} as any })

const reportQuery = computed(() => {
  if (props.report.key === 'custom') return { dims: builder.dims, measure: builder.measure, compare: builder.compare, order: builder.order }
  return { report: props.report.key }
})
const exportUrl = (fmt: string) => `/sales/reports/export/${fmt}?` + new URLSearchParams([
  ...Object.entries(props.filters).map(([k, v]) => [k, String(v)]),
  ...Object.entries(reportQuery.value).flatMap(([k, v]) => Array.isArray(v) ? v.map(x => [`${k}[]`, x]) : [[k, String(v)]]),
] as [string, string][]).toString()

function open(key: string) { router.get('/sales/reports', { ...props.filters, report: key }) }
function runBuilder() { router.get('/sales/reports', { ...props.filters, dims: builder.dims, measure: builder.measure, compare: builder.compare, order: builder.order }) }
function save() {
  saveForm.definition = { ...builder, filters: props.filters }
  saveForm.post('/sales/reports/saved', { preserveScroll: true, onSuccess: () => saveForm.reset() })
}
const dimCols = computed(() => props.result.kind === 'grouped' && props.result.rows?.length ? Object.keys(props.result.rows[0]).filter(k => k in props.dimensions) : [])
const hasCompare = computed(() => props.result.rows?.[0] && 'previous' in props.result.rows[0])
const num = (n: any) => Number(n ?? 0).toLocaleString('en-ZA', { maximumFractionDigits: 0 })
const isMoney = computed(() => ['net_value', 'gross_value', 'discount'].includes(props.report.measure ?? 'net_value'))
const showCases = computed(() => isMoney.value && props.result.rows?.[0] && 'cases' in props.result.rows[0])
const statusColor: Record<string, string> = { achieved: 'success', on_track: 'info', at_risk: 'warning', behind: 'error', breached: 'error' }
</script>

<template>
  <VContainer fluid class="pa-4 pa-sm-6">
    <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-3">
      <div><h3 class="text-h5 font-weight-bold mb-1">{{ report.title }}</h3><p class="text-body-2 text-medium-emphasis mb-0">{{ filters.from }} to {{ filters.to }}</p></div>
      <div v-if="canExport" class="d-flex gap-2">
        <VBtn v-for="f in ['xlsx', 'csv', 'pdf']" :key="f" variant="tonal" size="small" prepend-icon="tabler-download" :href="exportUrl(f)">{{ f.toUpperCase() }}</VBtn>
        <VBtn variant="text" size="small" prepend-icon="tabler-calendar-time" href="/sales/reports/schedules">Email schedules</VBtn>
      </div>
    </div>

    <VRow>
      <VCol cols="12" md="3">
        <VCard elevation="0" border rounded="lg" class="mb-4" title="Standard reports">
          <VList density="compact" nav>
            <VListItem v-for="r in catalogue" :key="r.key" :title="r.title" :active="report.key === r.key" color="primary" @click="open(r.key)" />
          </VList>
        </VCard>
        <VCard v-if="saved.length" elevation="0" border rounded="lg" title="Saved reports">
          <VList density="compact" nav>
            <VListItem v-for="s in saved" :key="s.id" :title="s.name" :subtitle="s.shared ? 'shared' : ''" @click="router.get('/sales/reports', { ...filters, saved: s.id })">
              <template #append><VBtn icon="tabler-x" size="x-small" variant="text" @click.stop="router.delete(`/sales/reports/saved/${s.id}`)" /></template>
            </VListItem>
          </VList>
        </VCard>
      </VCol>

      <VCol cols="12" md="9">
        <FilterBar url="/sales/reports" :filters="filters" :options="options" :extra="reportQuery" />

        <VCard elevation="0" border rounded="lg" class="mb-4" title="Report builder" subtitle="Choose what to group by, what to measure and what to compare with">
          <VCardText class="d-flex flex-wrap gap-3 align-center">
            <VSelect v-model="builder.dims" :items="Object.entries(dimensions).map(([v, t]) => ({ value: v, title: t }))" label="Group by (up to 3)" multiple chips closable-chips density="compact" variant="outlined" hide-details style="min-width: 280px; max-width: 420px" />
            <VSelect v-model="builder.measure" :items="Object.entries(measures).map(([v, t]) => ({ value: v, title: t }))" label="Measure" density="compact" variant="outlined" hide-details style="max-width: 180px" />
            <VSelect v-model="builder.compare" :items="[{ value: 'none', title: 'No comparison' }, { value: 'previous_year', title: 'Same period last year' }, { value: 'previous_period', title: 'Previous period' }]" label="Compare" density="compact" variant="outlined" hide-details style="max-width: 220px" />
            <VSelect v-model="builder.order" :items="[{ value: 'desc', title: 'Largest first' }, { value: 'asc', title: 'Smallest first' }, { value: 'growth', title: 'Fastest growth' }, { value: 'decline', title: 'Largest decline' }, { value: 'chronological', title: 'Chronological' }]" label="Sort" density="compact" variant="outlined" hide-details style="max-width: 180px" />
            <VBtn color="primary" @click="runBuilder">Run</VBtn>
            <VTextField v-model="saveForm.name" label="Save as" density="compact" variant="outlined" hide-details style="max-width: 200px" />
            <VCheckbox v-model="saveForm.shared" label="Share" density="compact" hide-details />
            <VBtn variant="tonal" :disabled="!saveForm.name" :loading="saveForm.processing" @click="save">Save</VBtn>
          </VCardText>
        </VCard>

        <VCard elevation="0" border rounded="lg">
          <template v-if="result.kind === 'grouped'">
            <VCardText class="pb-0 text-caption text-medium-emphasis">{{ result.count }} rows · total {{ money(result.total) }}</VCardText>
            <VTable density="compact" fixed-header height="600">
              <thead><tr><th v-for="d in dimCols" :key="d">{{ dimensions[d] }}</th><th class="text-end">{{ measures[report.measure ?? 'net_value'] }}</th><th v-if="showCases" class="text-end">Cases</th><th class="text-end">Share</th>
                <template v-if="hasCompare"><th class="text-end">Comparison</th><th class="text-end">Change</th><th class="text-end">Growth</th></template></tr></thead>
              <tbody><tr v-for="r in result.rows" :key="r.key">
                <td v-for="d in dimCols" :key="d">{{ r[d] }}</td><td class="text-end">{{ isMoney ? money(r.value) : num(r.value) }}</td><td v-if="showCases" class="text-end">{{ num(r.cases) }}</td><td class="text-end">{{ r.share }}%</td>
                <template v-if="hasCompare"><td class="text-end">{{ isMoney ? money(r.previous) : num(r.previous) }}</td><td class="text-end" :class="r.change >= 0 ? 'text-success' : 'text-error'">{{ isMoney ? money(r.change) : num(r.change) }}</td><td class="text-end">{{ pct(r.growth) }}</td></template>
              </tr></tbody>
            </VTable>
          </template>
          <VTable v-else-if="result.kind === 'targets'" density="compact">
            <thead><tr><th>Target</th><th>Period</th><th class="text-end">Target</th><th class="text-end">Actual</th><th class="text-end">Achievement</th><th class="text-end">Variance</th><th class="text-end">Forecast</th><th>Status</th></tr></thead>
            <tbody><tr v-for="t in result.rows" :key="t.uuid"><td>{{ t.name }}</td><td class="text-caption">{{ t.start_date }} → {{ t.end_date }}</td><td class="text-end">{{ money(t.performance.target) }}</td><td class="text-end">{{ money(t.performance.actual) }}</td>
              <td class="text-end">{{ t.performance.achievement }}%</td><td class="text-end" :class="t.performance.variance >= 0 ? 'text-success' : 'text-error'">{{ money(t.performance.variance) }}</td><td class="text-end">{{ money(t.performance.forecast) }}</td>
              <td><VChip size="x-small" :color="statusColor[t.performance.status]" variant="tonal">{{ t.performance.status }}</VChip></td></tr>
              <tr v-if="!result.rows.length"><td colspan="8" class="text-medium-emphasis">No approved targets overlap this period.</td></tr></tbody>
          </VTable>
          <VTable v-else-if="result.kind === 'forecast'" density="compact">
            <thead><tr><th>Month</th><th class="text-end">Actual</th><th class="text-end">Forecast</th><th class="text-end">Range</th><th>Method</th></tr></thead>
            <tbody><tr v-for="m in result.months" :key="m.month"><td>{{ m.month }}</td><td class="text-end">{{ m.actual === null ? '—' : money(m.actual) }}</td><td class="text-end">{{ money(m.forecast) }}</td><td class="text-end text-caption">{{ money(m.low) }} – {{ money(m.high) }}</td><td class="text-caption">{{ m.method }}</td></tr></tbody>
          </VTable>
          <VTable v-else-if="result.kind === 'imports'" density="compact">
            <thead><tr><th>File</th><th>Source</th><th>Status</th><th class="text-end">Rows</th><th class="text-end">Added</th><th class="text-end">Invalid</th><th class="text-end">Duplicates</th><th class="text-end">Net value</th><th class="text-end">Sender total</th></tr></thead>
            <tbody><tr v-for="b in result.rows" :key="b.uuid" class="cursor-pointer" @click="router.get(`/sales/imports/${b.uuid}`)"><td>{{ b.file_name }}</td><td>{{ b.source }}</td><td>{{ b.status }}</td><td class="text-end">{{ b.total_rows }}</td><td class="text-end">{{ b.inserted_rows }}</td><td class="text-end">{{ b.invalid_rows }}</td><td class="text-end">{{ b.duplicate_rows }}</td>
              <td class="text-end">{{ money(b.totals?.net_value) }}</td><td class="text-end">{{ b.source_totals?.net_value ? money(b.source_totals.net_value) : '—' }}</td></tr></tbody>
          </VTable>
          <VTable v-else density="compact"><tbody><tr v-for="q in result.rows" :key="q.check"><td>{{ q.check }}</td><td class="text-end font-weight-bold" :class="q.value ? 'text-warning' : 'text-success'">{{ q.value }}</td></tr></tbody></VTable>
        </VCard>
      </VCol>
    </VRow>
  </VContainer>
</template>
