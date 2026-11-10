<script setup lang="ts">
import axios from 'axios'
import * as echarts from 'echarts'
import VChart from 'vue-echarts'
import { computed, onMounted, ref, watch } from 'vue'
import { buildOption, fmt, GOOD, AMBER, BAD, visual } from './visuals'
import GeoMap from './GeoMap.vue'
import Influencers from './Influencers.vue'
import DecompTree from './DecompTree.vue'

const props = withDefaults(defineProps<{ widget: any; filters: Record<string, any>; editing?: boolean; endpoint?: string; shared?: boolean }>(), { endpoint: '/sales/bi/query', shared: false })
const emit = defineEmits<{ filter: [{ dimension: string; key: any; label: string }]; edit: []; remove: []; duplicate: []; comment: [] }>()
void echarts

const v = computed(() => visual(props.widget.type))
const data = ref<any>(null)
const loading = ref(false)
const error = ref('')
const chart = ref<any>(null)
const ai = ref('')
const aiBusy = ref(false)
const textValue = computed(() => props.widget.config?.text ?? '')
const special = computed(() => v.value.special)

async function load() {
  if (special.value === 'text' || special.value === 'tree') return
  loading.value = true; error.value = ''
  try {
    if (props.shared && ['status', 'targets', 'ai'].includes(special.value ?? '')) { data.value = { unavailable: true }; return }
    if (special.value === 'status' || special.value === 'targets') {
      data.value = (await axios.get('/sales/bi/status', { params: { filters: props.filters } })).data
    } else {
      const config = effectiveConfig.value
      data.value = (await axios.post(props.endpoint, { config, filters: props.filters })).data
    }
  } catch (e: any) { error.value = e.response?.data?.message ?? 'Could not load this visual.' } finally { loading.value = false }
}
onMounted(load)
watch(() => [props.filters, props.widget.config, props.widget.type], () => { drill.value = []; load() }, { deep: true })

// ── Drill-down (gap 4): time year → quarter → month → week → day; category → product, region → customer… ──
const GRAIN_NEXT: Record<string, string> = { year: 'quarter', quarter: 'month', month: 'week', week: 'day' }
const DIM_NEXT: Record<string, string> = { category: 'product', brand: 'product', customer_group: 'customer', region: 'customer', province: 'city', city: 'customer',
  sales_team: 'salesperson', salesperson: 'customer', business_unit: 'customer', distribution_centre: 'customer' }
const DIM_LABEL: Record<string, string> = { quarter: 'quarters', month: 'months', week: 'weeks', day: 'days', product: 'products', customer: 'customers', city: 'cities', salesperson: 'salespeople' }
const drill = ref<{ label: string; patch: Record<string, any> }[]>([])
const drillMode = ref(false)
const baseConfig = computed(() => ({ ...v.value.defaults, ...(props.widget.config ?? {}), kind: v.value.kind }))
const effectiveConfig = computed(() => drill.value.reduce((c: any, step) => ({ ...c, ...step.patch, filters: { ...(c.filters ?? {}), ...(step.patch.filters ?? {}) } }), { ...baseConfig.value }))
const drillable = computed(() => {
  const c: any = effectiveConfig.value
  if (c.kind === 'time' && !c.series) return !!GRAIN_NEXT[c.grain ?? 'month']
  if (['category', 'table'].includes(c.kind)) return !!DIM_NEXT[c.dimension]
  return false
})
const iso = (d: Date) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10)
function bucketEnd(start: string, grain: string): string {
  const d = new Date(start + 'T00:00:00')
  const end = grain === 'year' ? new Date(d.getFullYear(), 11, 31) : grain === 'quarter' ? new Date(d.getFullYear(), d.getMonth() + 3, 0)
    : grain === 'month' ? new Date(d.getFullYear(), d.getMonth() + 1, 0) : new Date(d.getFullYear(), d.getMonth(), d.getDate() + 6)
  return iso(end)
}
function drillInto(index: number, label: string) {
  const c: any = effectiveConfig.value, d = data.value
  const key = d?.keys?.[index]
  if (key === undefined || key === 'other') return
  if (c.kind === 'time') {
    const grain = c.grain ?? 'month'
    drill.value.push({ label, patch: { grain: GRAIN_NEXT[grain], period: 'dashboard', filters: { from: String(key).slice(0, 10), to: bucketEnd(String(key).slice(0, 10), grain) } } })
  } else {
    const dim = c.dimension, fk = ['province', 'city'].includes(dim) ? dim : `${dim}_id`
    drill.value.push({ label, patch: { dimension: DIM_NEXT[dim], filters: { [fk]: key } } })
  }
  load()
}
function drillUp(to: number) { drill.value = drill.value.slice(0, to); load() }
const nextLevel = computed(() => { const c: any = effectiveConfig.value; return DIM_LABEL[c.kind === 'time' ? GRAIN_NEXT[c.grain ?? 'month'] : DIM_NEXT[c.dimension]] ?? '' })
const ownFilters = computed(() => Object.entries(props.widget.config?.filters ?? {}).filter(([, x]) => x !== null && x !== '' && x !== undefined).length)

const option = computed(() => data.value && !['kpi', 'table', 'narrative', 'decomposition', 'none', 'geo', 'influencers'].includes(v.value.kind) && !['matrix_table', 'variance_table', 'ranking_table'].includes(props.widget.type) ? buildOption(props.widget.type, data.value) : null)
const isEmpty = computed(() => {
  const d = data.value
  if (!d) return false
  if (d.series) return !d.series.some((s: any) => s.data?.some((x: any) => x))
  if (d.nodes) return !d.links?.length
  if (d.regions) return !d.regions.length
  if (d.decline) return !d.decline.length && !d.growth.length
  if (d.points) return !d.points.length
  if (d.tree) return !d.tree.length
  if (d.cells) return !d.cells.length
  if (d.counts) return !d.counts.length
  return false
})

// ── KPI helpers ──
const vals = computed(() => data.value?.values ?? {})
const m0 = computed(() => props.widget.config?.measures?.[0] ?? v.value.defaults?.measures?.[0] ?? 'net_value')
const m1 = computed(() => props.widget.config?.measures?.[1] ?? v.value.defaults?.measures?.[1])
const f0 = computed(() => data.value?.formats?.[m0.value] ?? 'currency')
// Value targets (rand) or volume targets (cases): the same cards serve both.
const isVolume = computed(() => [m0.value, m1.value].includes('volume_target') || m0.value === 'volume_achievement_pct')
const tKey = computed(() => (isVolume.value ? 'volume_target' : 'target'))
const aKey = computed(() => (isVolume.value ? 'quantity' : 'net_value'))
const tFmt = computed(() => (isVolume.value ? 'number' : 'currency'))
const ach = computed(() => {
  const t = Number(vals.value[tKey.value] ?? 0)
  const pct = isVolume.value ? vals.value.volume_achievement_pct : vals.value.achievement_pct
  return Number(pct ?? (t ? Number(vals.value[aKey.value] ?? 0) / t * 100 : 0))
})
const monthDone = computed(() => (vals.value.month_target ? Number(vals.value.net_value ?? 0) / vals.value.month_target * 100 : 0))
// Share of the month gone, measured to the latest day with sales (the same day the run rate uses).
const monthGone = computed(() => {
  const to = data.value?.period?.to ? new Date(data.value.period.to) : new Date()
  const days = new Date(to.getFullYear(), to.getMonth() + 1, 0).getDate()
  return { day: to.getDate(), days, pct: to.getDate() / days * 100, label: to.toLocaleDateString('en-ZA', { day: 'numeric', month: 'short' }) }
})
const status = (p: number) => (p >= 100 ? GOOD : p >= 80 ? AMBER : BAD)
const statusText = (p: number) => (p >= 100 ? 'On or above target' : p >= 80 ? 'Close to target' : 'Below target')
const runRate = computed(() => {
  const from = new Date(data.value?.period?.from), to = new Date(data.value?.period?.to)
  const days = to.getDate(), inMonth = new Date(to.getFullYear(), to.getMonth() + 1, 0).getDate()
  // The full month's target, never the part pro-rated to date.
  const t = vals.value.month_target ?? null
  return { forecast: (vals.value.net_value ?? 0) / Math.max(1, days) * inMonth, needed: t ? Math.max(0, (t - vals.value.net_value) / Math.max(1, inMonth - days)) : null, target: t, from }
})
const sparkOption = computed(() => ({ grid: { left: 0, right: 0, top: 4, bottom: 0 }, xAxis: { type: 'category', show: false }, yAxis: { type: 'value', show: false, scale: true },
  series: [{ type: 'line', smooth: true, symbol: 'none', data: data.value?.spark ?? [], lineStyle: { width: 2, color: '#6A0DAD' }, areaStyle: { color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [{ offset: 0, color: 'rgba(106,13,173,.25)' }, { offset: 1, color: 'rgba(106,13,173,0)' }]) } }] }))
const gaugeOption = computed(() => ({ series: [{ type: 'gauge', startAngle: 210, endAngle: -30, min: 0, max: 120, radius: '92%', center: ['50%', '58%'], progress: { show: true, width: 12, roundCap: true, itemStyle: { color: status(ach.value) } },
  axisLine: { lineStyle: { width: 12, color: [[1, '#F1EDF7']] } }, axisTick: { show: false }, splitLine: { show: false }, axisLabel: { show: false }, pointer: { show: false }, anchor: { show: false }, title: { show: true, offsetCenter: [0, '42%'], fontSize: 11, color: '#77778A' },
  detail: { valueAnimation: true, offsetCenter: [0, '2%'], fontSize: 22, fontWeight: 700, color: '#1F1F29', formatter: (x: number) => x.toFixed(1) + '%' }, data: [{ value: +ach.value.toFixed(1), name: statusText(ach.value) }] }] }))

// ── Interactions ──
const filterable = ['customer', 'product', 'category', 'brand', 'region', 'customer_group', 'sales_team', 'salesperson', 'business_unit', 'distribution_centre', 'province', 'city']
function onClick(p: any) {
  const d = data.value
  if (drillMode.value && drillable.value && d?.keys) {
    const horizontal = props.widget.type.startsWith('bar') || props.widget.type === 'lollipop' || props.widget.type === 'diverging_bar'
    const i = p.dataIndex !== undefined ? (horizontal && d.dimension ? d.keys.length - 1 - p.dataIndex : p.dataIndex) : -1
    if (i >= 0) drillInto(i, p.name ?? d.categories?.[i])
    return
  }
  if (d?.nodes) {
    if (p.dataType === 'node' && filterable.includes(d.dimensions[p.data.category])) emit('filter', { dimension: d.dimensions[p.data.category], key: p.data.key, label: p.data.name })
    return
  }
  const dim = d?.dimension ?? props.widget.config?.dimension
  if (!dim || !filterable.includes(dim) || !d?.keys) return
  const key = d.keys[p.dataIndex !== undefined ? (props.widget.type.startsWith('bar') || props.widget.type === 'lollipop' || props.widget.type === 'diverging_bar' ? d.keys.length - 1 - p.dataIndex : p.dataIndex) : -1]
  if (key !== undefined && key !== 'other') emit('filter', { dimension: dim, key, label: p.name })
}
// "Alert me on this" (gap 3): open My alerts with this visual's number and filters filled in.
const alertable = computed(() => ['kpi', 'time', 'category', 'table'].includes(v.value.kind) && !special.value)
function alertMe() {
  const c: any = effectiveConfig.value
  const measure = (c.measures ?? ['net_value']).find((m: string) => !['last_year', 'growth_pct', 'target', 'achievement_pct', 'variance', 'volume_target', 'volume_achievement_pct', 'month_target'].includes(m)) ?? 'net_value'
  const q = new URLSearchParams({ measure, name: props.widget.title ?? '', filters: JSON.stringify({ ...props.filters, ...(c.filters ?? {}), from: undefined, to: undefined }) })
  window.location.href = '/sales/alerts?' + q.toString()
}
// Board pack (gap 10): a picture of the chart, or the text of a number card / table.
const cardEl = ref<HTMLElement | null>(null)
function snapshot() {
  const image = chart.value?.getDataURL?.({ type: 'png', pixelRatio: 1.5, backgroundColor: '#fff' }) ?? null
  const text = image ? null : (cardEl.value?.querySelector('.bi-card__body') as HTMLElement | null)?.innerText?.trim().slice(0, 600) ?? null
  return { title: props.widget.title || v.value.name, image, text }
}
defineExpose({ snapshot })
function drillThrough() {
  const q = new URLSearchParams(Object.entries(props.filters).map(([k, x]) => [k, String(x)]))
  window.location.href = '/sales/records?' + q.toString()
}
function exportPng() {
  const url = chart.value?.getDataURL?.({ pixelRatio: 2, backgroundColor: '#fff' })
  if (!url) return
  const a = document.createElement('a'); a.href = url; a.download = (props.widget.title || 'chart') + '.png'; a.click()
}
async function askAi() {
  aiBusy.value = true
  try { ai.value = (await axios.post('/ai-copilot/chat', { message: 'In five short bullet points, what should management know about our sales right now? Quote figures and periods, separate facts from recommendations.' })).data.message?.content ?? '' } catch { ai.value = 'The AI assistant is not available right now.' } finally { aiBusy.value = false }
}

// ── Tables ──
const tableRows = computed(() => {
  const d = data.value
  if (!d?.series) return []
  return d.categories.map((c: string, i: number) => ({ label: c, key: d.keys?.[i], values: d.series.map((s: any) => s.data[i]) }))
})
const tableSort = ref<{ i: number; dir: 1 | -1 } | null>(null)
const sortedRows = computed(() => !tableSort.value ? tableRows.value : [...tableRows.value].sort((a, b) => ((a.values[tableSort.value!.i] ?? -Infinity) - (b.values[tableSort.value!.i] ?? -Infinity)) * tableSort.value!.dir))
const heat = (val: number, max: number) => `rgba(106,13,173,${Math.min(0.85, 0.08 + (max ? val / max : 0) * 0.77)})`
</script>

<template>
  <div ref="cardEl" class="bi-card">
    <div class="bi-card__head">
      <div class="bi-card__title" :title="widget.title">{{ widget.title || v.name }}</div>
      <div class="bi-card__actions">
        <VProgressCircular v-if="loading" indeterminate size="14" width="2" color="primary" class="me-1" />
        <VChip v-if="ownFilters" size="x-small" variant="tonal" color="primary" class="me-1" title="This visual has its own filters">Filtered</VChip>
        <VBtn v-if="drillable && !editing" :icon="drillMode ? 'tabler-arrow-down-right-circle' : 'tabler-arrow-down-right'" size="x-small" :variant="drillMode ? 'tonal' : 'text'" :color="drillMode ? 'primary' : undefined"
              :title="drillMode ? `Drill mode on: click a bar to see its ${nextLevel}` : `Drill down into ${nextLevel}`" @click="drillMode = !drillMode" />
        <template v-if="editing">
          <VBtn icon="tabler-settings" size="x-small" variant="text" @click="emit('edit')" />
          <VBtn icon="tabler-copy" size="x-small" variant="text" @click="emit('duplicate')" />
          <VBtn icon="tabler-trash" size="x-small" variant="text" color="error" @click="emit('remove')" />
        </template>
        <VMenu v-else-if="!shared" location="bottom end">
          <template #activator="{ props: p }"><VBtn v-bind="p" icon="tabler-dots" size="x-small" variant="text" class="bi-card__menu" /></template>
          <VList density="compact">
            <VListItem prepend-icon="tabler-table" title="See the records" @click="drillThrough" />
            <VListItem v-if="option" prepend-icon="tabler-photo-down" title="Download PNG" @click="exportPng" />
            <VListItem prepend-icon="tabler-refresh" title="Refresh" @click="load" />
            <VListItem prepend-icon="tabler-message-circle" title="Comment on this visual" @click="emit('comment')" />
            <VListItem v-if="alertable" prepend-icon="tabler-bell-plus" title="Alert me on this" @click="alertMe" />
          </VList>
        </VMenu>
      </div>
    </div>

    <div v-if="drill.length" class="bi-crumbs">
      <button @click="drillUp(0)"><VIcon icon="tabler-arrow-up-left" size="13" /> All</button>
      <template v-for="(step, i) in drill" :key="i"><span>›</span><button :class="{ on: i === drill.length - 1 }" @click="drillUp(i + 1)">{{ step.label }}</button></template>
    </div>
    <div class="bi-card__body">
      <div v-if="error" class="bi-empty"><VIcon icon="tabler-alert-circle" color="error" />{{ error }}</div>
      <DecompTree v-else-if="special === 'tree'" :widget="widget" :filters="filters" :endpoint="endpoint" />
      <div v-else-if="data?.unavailable" class="bi-empty"><VIcon icon="tabler-lock" size="24" />Not included in a shared link.</div>
      <div v-else-if="special === 'text'" class="bi-text">{{ textValue || 'Add your text in the visual settings.' }}</div>
      <div v-else-if="!data && loading" class="bi-skeleton" />
      <template v-else-if="data">
        <!-- KPI family -->
        <template v-if="v.kind === 'kpi' && !special">
          <div v-if="widget.type === 'gauge'" class="bi-gauge"><VChart :option="gaugeOption" autoresize /></div>
          <div v-else-if="['linear_gauge', 'progress', 'thermometer'].includes(widget.type)" class="bi-kpi">
            <div class="bi-kpi__value">{{ fmt(ach, 'percent') }}</div>
            <div class="bi-bar" :class="{ 'bi-bar--thick': widget.type === 'thermometer' }"><span :style="{ width: Math.min(100, ach) + '%', background: status(ach) }" /><i style="left: 100%" /></div>
            <div class="bi-kpi__note">{{ fmt(vals.net_value) }} of {{ fmt(vals.target) }} target · {{ statusText(ach) }}</div>
          </div>
          <div v-else-if="widget.type === 'traffic_light'" class="bi-kpi bi-kpi--center">
            <div class="bi-light"><span v-for="c in [BAD, AMBER, GOOD]" :key="c" :style="{ background: status(ach) === c ? c : '#ECECF1' }" /></div>
            <div class="bi-kpi__note mt-2"><strong>{{ fmt(ach, 'percent') }}</strong> · {{ statusText(ach) }}</div>
          </div>
          <div v-else-if="widget.type === 'bullet' || widget.type === 'kpi_target'" class="bi-kpi">
            <div class="bi-kpi__value">{{ fmt(widget.type === 'kpi_target' ? vals[tKey] : vals[aKey], tFmt) }}<small v-if="isVolume" class="bi-kpi__unit"> cases</small></div>
            <div class="bi-bullet"><em style="width: 80%" /><em style="width: 100%" /><span :style="{ width: Math.min(120, ach) / 1.2 + '%', background: status(ach) }" /><i :style="{ left: 100 / 1.2 + '%' }" /></div>
            <div class="bi-kpi__note"><template v-if="vals[tKey]">{{ widget.type === 'kpi_target' ? `Actual ${fmt(vals[aKey], tFmt)}` : `Target ${fmt(vals[tKey], tFmt)}` }} · <span :style="{ color: status(ach) }">{{ fmt(ach, 'percent') }} achieved</span></template><template v-else>No approved {{ isVolume ? 'volume' : 'sales' }} target for this period</template></div>
          </div>
          <div v-else-if="widget.type === 'month_elapsed'" class="bi-kpi">
            <div class="bi-kpi__value">{{ Math.round(monthGone.pct) }}%<small class="bi-kpi__unit"> of month gone</small></div>
            <div class="bi-bar"><span :style="{ width: monthGone.pct + '%', background: '#6A0DAD' }" /></div>
            <div class="bi-kpi__lines">
              <div>Day {{ monthGone.day }} of {{ monthGone.days }} · sales up to {{ monthGone.label }}</div>
              <div v-if="vals.month_target"><b :style="{ color: monthDone >= monthGone.pct ? GOOD : BAD }">{{ fmt(monthDone, 'percent') }}</b> of the month's target sold</div>
            </div>
          </div>
          <div v-else-if="widget.type === 'variance_card'" class="bi-kpi">
            <div class="bi-kpi__value" :style="{ color: (vals[m0] ?? 0) >= 0 ? GOOD : BAD }">{{ (vals[m0] ?? 0) >= 0 && f0 === 'percent' ? '+' : '' }}{{ fmt(vals[m0], f0) }}</div>
            <div class="bi-kpi__note">{{ m1 ? `vs ${fmt(vals[m1], data.formats?.[m1])}` : '' }}</div>
          </div>
          <div v-else-if="widget.type === 'run_rate_card'" class="bi-kpi">
            <div class="bi-kpi__value">{{ fmt(runRate.forecast) }}</div>
            <div class="bi-kpi__lines">
              <div>At the current daily rate</div>
              <div v-if="runRate.target">Target {{ fmt(runRate.target) }} · <b :style="{ color: runRate.forecast >= runRate.target ? GOOD : BAD }">{{ fmt(runRate.forecast / runRate.target * 100, 'percent') }}</b></div>
              <div v-if="runRate.needed">Needs {{ fmt(runRate.needed) }} a day from here</div>
            </div>
          </div>
          <div v-else-if="widget.type === 'multi_kpi'" class="bi-multi">
            <div v-for="m in widget.config?.measures ?? []" :key="m"><small>{{ data.labels?.[m] }}</small><strong>{{ fmt(vals[m], data.formats?.[m]) }}</strong></div>
          </div>
          <div v-else class="bi-kpi">
            <div class="bi-kpi__value">{{ fmt(vals[m0], f0) }}</div>
            <div v-if="m1" class="bi-kpi__note"><span v-if="m1 === 'growth_pct'" :style="{ color: (vals.growth_pct ?? 0) >= 0 ? GOOD : BAD }"><VIcon :icon="(vals.growth_pct ?? 0) >= 0 ? 'tabler-arrow-up-right' : 'tabler-arrow-down-right'" size="14" />{{ fmt(Math.abs(vals.growth_pct ?? 0), 'percent') }} vs last year</span><span v-else>{{ data.labels?.[m1] }}: {{ fmt(vals[m1], data.formats?.[m1]) }}</span></div>
            <div v-if="widget.type === 'kpi_sparkline'" class="bi-spark"><VChart :option="sparkOption" autoresize /></div>
          </div>
        </template>

        <!-- Maps & influencers -->
        <template v-else-if="special === 'geo'">
          <div v-if="!data.located" class="bi-empty"><VIcon icon="tabler-map-pin-off" size="28" /><div><strong>No customer has a location yet.</strong><br>Add latitude and longitude (or at least a province) to customers — in Master data or with the customer import — and this map plots them.</div></div>
          <GeoMap v-else :data="data" :type="widget.type" @filter="e => emit('filter', e)" />
        </template>
        <Influencers v-else-if="special === 'influencers'" :data="data" @filter="e => emit('filter', e)" />

        <!-- Text -->
        <div v-else-if="special === 'ai'" class="bi-text">
          <VChip size="x-small" color="warning" variant="tonal" class="mb-2">AI-generated · review before acting</VChip>
          <div v-if="ai" style="white-space: pre-wrap">{{ ai }}</div>
          <VBtn v-else size="small" color="primary" variant="tonal" prepend-icon="tabler-sparkles" :loading="aiBusy" @click="askAi">Generate insights</VBtn>
        </div>
        <ul v-else-if="v.kind === 'narrative'" class="bi-narrative"><li v-for="l in data.lines" :key="l">{{ l }}</li></ul>

        <!-- Status / scorecard -->
        <div v-else-if="special === 'status'" class="bi-status">
          <div><small>Data up to</small><strong>{{ data.last_sale ?? '—' }}</strong></div>
          <div><small>Last report by email</small><strong>{{ data.last_email ? new Date(data.last_email).toLocaleString('en-ZA', { dateStyle: 'medium', timeStyle: 'short' }) : 'not yet' }}</strong></div>
          <div><small>Imports waiting</small><strong :class="data.awaiting ? 'text-warning' : ''">{{ data.awaiting }}</strong></div>
          <div><small>Need review</small><strong :class="data.needs_review ? 'text-warning' : ''">{{ data.needs_review }}</strong></div>
          <div class="bi-status__imports"><small>Latest imports</small>
            <a v-for="b in data.imports" :key="b.uuid" :href="`/sales/imports/${b.uuid}`"><VChip size="x-small" :color="b.status === 'committed' ? 'success' : b.status === 'failed' ? 'error' : 'warning'" variant="tonal">{{ b.status }}</VChip> {{ b.file_name }}</a>
          </div>
        </div>
        <div v-else-if="special === 'targets'" class="bi-scroll">
          <table class="bi-table"><thead><tr><th>Target</th><th class="r">Target</th><th class="r">Actual</th><th style="width: 30%">Achievement</th></tr></thead>
            <tbody><tr v-for="t in data.targets" :key="t.uuid"><td><a :href="`/sales/targets/${t.uuid}`">{{ t.name }}</a></td><td class="r">{{ fmt(t.performance.target, t.metric.includes('value') ? 'currency' : 'number') }}</td><td class="r">{{ fmt(t.performance.actual, t.metric.includes('value') ? 'currency' : 'number') }}</td>
              <td><div class="bi-bar"><span :style="{ width: Math.min(100, t.performance.achievement ?? 0) + '%', background: status(t.performance.achievement ?? 0) }" /></div></td></tr>
              <tr v-if="!data.targets.length"><td colspan="4" class="bi-muted">No approved targets this month.</td></tr></tbody></table>
        </div>

        <!-- Tables -->
        <div v-else-if="widget.type === 'matrix_table'" class="bi-scroll">
          <table class="bi-table bi-table--matrix"><thead><tr><th /><th v-for="c in data.columns" :key="c" class="r">{{ c }}</th></tr></thead>
            <tbody><tr v-for="(r, ri) in data.rows" :key="r"><td>{{ r }}</td><td v-for="(c, ci) in data.columns" :key="c" class="r" :style="{ background: heat((data.cells.find((x: any) => x[0] === ci && x[1] === ri) ?? [0, 0, 0])[2], data.max), color: (data.cells.find((x: any) => x[0] === ci && x[1] === ri) ?? [0, 0, 0])[2] / (data.max || 1) > .55 ? '#fff' : '#1F1F29' }">{{ fmt((data.cells.find((x: any) => x[0] === ci && x[1] === ri) ?? [0, 0, 0])[2]) }}</td></tr></tbody></table>
        </div>
        <div v-else-if="['table', 'ranking_table', 'variance_table', 'decomposition'].includes(widget.type)" class="bi-scroll">
          <table class="bi-table"><thead><tr><th>{{ data.dimension ? data.dimension.replace('_', ' ') : 'Period' }}</th>
            <th v-for="(s, i) in data.series" :key="s.key" class="r sortable" @click="tableSort = { i, dir: tableSort?.i === i && tableSort.dir === -1 ? 1 : -1 }">{{ s.name }} <VIcon v-if="tableSort?.i === i" :icon="tableSort.dir === -1 ? 'tabler-chevron-down' : 'tabler-chevron-up'" size="12" /></th></tr></thead>
            <tbody><tr v-for="(r, i) in sortedRows" :key="r.label" :class="{ clickable: data.dimension }" @click="onClick({ dataIndex: tableRows.indexOf(r), name: r.label })">
              <td><span v-if="widget.type === 'ranking_table'" class="bi-rank">{{ i + 1 }}</span>{{ r.label }}</td>
              <td v-for="(val, j) in r.values" :key="j" class="r" :style="{ color: data.series[j].measure.includes('pct') || data.series[j].measure === 'variance' ? (val ?? 0) >= 0 ? GOOD : BAD : undefined }">{{ fmt(val, data.formats?.[data.series[j].measure], false) }}</td></tr></tbody>
            <tfoot v-if="data.total !== undefined"><tr><td>Total ({{ data.count }})</td><td class="r">{{ fmt(data.total, 'currency', false) }}</td><td :colspan="Math.max(0, data.series.length - 1)" /></tr></tfoot></table>
        </div>

        <!-- Charts -->
        <div v-else-if="isEmpty" class="bi-empty"><VIcon icon="tabler-chart-dots" size="28" />No data for this selection.</div>
        <VChart v-else-if="option" ref="chart" class="bi-fill" :option="option" autoresize @click="onClick" />
      </template>
    </div>
  </div>
</template>

<style scoped>
.bi-card { height: 100%; display: flex; flex-direction: column; background: #fff; border: 1px solid #EEEEF3; border-radius: 18px; box-shadow: 0 1px 2px rgba(17,17,26,.03); overflow: hidden; transition: box-shadow .2s; }
.bi-card:hover { box-shadow: 0 10px 30px rgba(17,17,26,.06); }
.bi-card__head { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px 4px 18px; min-height: 40px; }
.bi-card__title { font-size: 13px; font-weight: 600; color: #3A3A4A; letter-spacing: .1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bi-card__actions { display: flex; align-items: center; }
.bi-card__menu { opacity: 0; transition: opacity .15s; }
.bi-card:hover .bi-card__menu { opacity: 1; }
.bi-crumbs { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; padding: 0 18px 4px; font-size: 12px; color: #8A8A99; }
.bi-crumbs button { border: 0; background: #F6F4FA; color: #5B5B6E; border-radius: 6px; padding: 1px 7px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 2px; }
.bi-crumbs button.on { background: #F3EEFA; color: #6A0DAD; }
.bi-card__body { flex: 1; min-height: 0; padding: 4px 14px 14px; position: relative; }
.bi-fill { width: 100%; height: 100%; }
.bi-kpi { display: flex; flex-direction: column; justify-content: center; height: 100%; padding: 0 4px; }
.bi-kpi--center { align-items: center; }
.bi-kpi__lines { font-size: 12px; color: #77778A; margin-top: 6px; line-height: 1.45; }
.bi-kpi__unit { font-size: 13px; font-weight: 600; color: #8A8A99; letter-spacing: 0; }
.bi-kpi__value { font-size: 28px; font-weight: 700; color: #16161F; letter-spacing: -.5px; line-height: 1.15; }
.bi-kpi__note { font-size: 12px; color: #77778A; margin-top: 6px; display: flex; align-items: center; gap: 2px; flex-wrap: wrap; }
.bi-spark { height: 38px; margin-top: 6px; }
.bi-multi { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 16px; height: 100%; align-content: center; }
.bi-multi small { display: block; color: #8A8A99; font-size: 11px; } .bi-multi strong { font-size: 18px; color: #16161F; }
.bi-bar { position: relative; height: 10px; border-radius: 99px; background: #F1EDF7; margin-top: 10px; overflow: visible; }
.bi-bar--thick { height: 22px; }
.bi-bar span { position: absolute; inset: 0 auto 0 0; border-radius: 99px; transition: width .6s; }
.bi-bullet { position: relative; height: 18px; margin-top: 12px; }
.bi-gauge { width: 100%; height: 100%; min-height: 110px; }
.bi-bullet em, .bi-bullet span, .bi-bullet i, .bi-bar span, .bi-bar i { display: block; }
.bi-bullet em { position: absolute; inset: 0 auto 0 0; background: #F1EDF7; border-radius: 6px; }
.bi-bullet em:first-child { background: #E6DCF3; z-index: 1; }
.bi-bullet span { position: absolute; top: 5px; bottom: 5px; left: 0; border-radius: 4px; z-index: 2; }
.bi-bullet i { position: absolute; top: -2px; bottom: -2px; width: 3px; background: #16161F; border-radius: 2px; z-index: 3; }
.bi-light { display: flex; gap: 8px; } .bi-light span { width: 26px; height: 26px; border-radius: 50%; transition: background .3s; }
.bi-narrative { margin: 0; padding-left: 18px; font-size: 13px; color: #3A3A4A; line-height: 1.6; } .bi-narrative li { margin-bottom: 4px; }
.bi-text { font-size: 13px; color: #3A3A4A; line-height: 1.6; overflow: auto; height: 100%; }
.bi-status { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)) 2fr; gap: 16px; align-items: start; height: 100%; }
.bi-status small { display: block; font-size: 11px; color: #8A8A99; margin-bottom: 2px; } .bi-status strong { font-size: 16px; color: #16161F; }
.bi-status__imports a { display: block; font-size: 12px; color: #3A3A4A; text-decoration: none; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.bi-scroll { height: 100%; overflow: auto; }
.bi-table { width: 100%; border-collapse: collapse; font-size: 12px; color: #2B2B38; }
.bi-table th { position: sticky; top: 0; background: #fff; text-align: left; font-weight: 600; color: #77778A; padding: 6px 8px; border-bottom: 1px solid #EEEEF3; white-space: nowrap; text-transform: capitalize; }
.bi-table td { padding: 6px 8px; border-bottom: 1px solid #F5F5F8; white-space: nowrap; max-width: 240px; overflow: hidden; text-overflow: ellipsis; }
.bi-table .r { text-align: right; font-variant-numeric: tabular-nums; }
.bi-table tfoot td { font-weight: 700; border-top: 1px solid #EEEEF3; }
.bi-table .sortable { cursor: pointer; } .bi-table tr.clickable { cursor: pointer; } .bi-table tr.clickable:hover td { background: #FAF8FD; }
.bi-table--matrix td.r { border: 2px solid #fff; border-radius: 4px; }
.bi-rank { display: inline-flex; width: 20px; height: 20px; border-radius: 6px; background: #F1EDF7; color: #6A0DAD; font-size: 10px; font-weight: 700; align-items: center; justify-content: center; margin-right: 8px; }
.bi-muted { color: #8A8A99; }
.bi-empty { height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: #8A8A99; font-size: 13px; text-align: center; padding: 0 12px; }
.bi-skeleton { height: 100%; border-radius: 12px; background: linear-gradient(90deg, #F6F5FA 25%, #EFEDF5 37%, #F6F5FA 63%); background-size: 400% 100%; animation: bi-shimmer 1.4s ease infinite; }
@keyframes bi-shimmer { 0% { background-position: 100% 50% } 100% { background-position: 0 50% } }
</style>
