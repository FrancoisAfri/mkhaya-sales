<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
import FilterBar from '@/Components/Sales/FilterBar.vue'

const props = defineProps<{
  view: 'executive' | 'customers' | 'products' | 'targets'
  kpis: any; filters: Record<string, any>; options: Record<string, any[]>
  trend?: { labels: string[]; current: number[]; previous: number[]; grain: string }
  topCustomers?: any[]; topProducts?: any[]; decliningCustomers?: any[]; decliningProducts?: any[]
  ranking?: any[]; groups?: any[]; regions?: any[]; categories?: any[]; brands?: any[]; growth?: any[]; volume?: any[]; quantity?: any[]
  concentration?: { top5_share: number; top10_share: number; customers: number }
  targets?: any[]; targetSummary?: any
}>()

const money = (n: any) => 'R ' + Number(n ?? 0).toLocaleString('en-ZA', { maximumFractionDigits: 0 })
const num = (n: any) => Number(n ?? 0).toLocaleString('en-ZA', { maximumFractionDigits: 0 })
const pct = (n: any) => n === null || n === undefined ? '—' : `${n > 0 ? '+' : ''}${n}%`
const tone = (n: any) => n === null || n === undefined ? '' : n >= 0 ? 'text-success' : 'text-error'
const when = (s: string | null) => s ? new Date(s).toLocaleString('en-ZA', { dateStyle: 'medium', timeStyle: 'short' }) : 'never'
const tabs = [['executive', 'Executive'], ['customers', 'Customers'], ['products', 'Products'], ['targets', 'Targets']]
const go = (v: string) => router.get(`/sales/dashboard${v === 'executive' ? '' : '/' + v}`, props.filters, { preserveState: false })

const trendChart = computed(() => ({
  series: [{ name: 'This period', data: props.trend?.current ?? [] }, { name: 'Same period last year', data: props.trend?.previous ?? [] }],
  options: {
    chart: { type: 'area', toolbar: { show: false }, fontFamily: 'inherit' }, stroke: { curve: 'smooth', width: [3, 2], dashArray: [0, 5] },
    colors: ['#6A0DAD', '#9e9e9e'], dataLabels: { enabled: false }, fill: { type: ['gradient', 'solid'], opacity: [0.25, 0] },
    xaxis: { categories: props.trend?.labels ?? [], labels: { rotate: -45 } }, legend: { position: 'top' },
    yaxis: { labels: { formatter: (v: number) => 'R ' + (v >= 1e6 ? (v / 1e6).toFixed(1) + 'm' : v >= 1e3 ? (v / 1e3).toFixed(0) + 'k' : v.toFixed(0)) } },
    tooltip: { y: { formatter: (v: number) => money(v) } },
  },
}))
const bar = (rows: any[] | undefined, dim: string, measure = 'value') => ({
  series: [{ name: measure === 'value' ? 'Net sales' : measure, data: (rows ?? []).slice(0, 10).map(r => r[measure]) }],
  options: {
    chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' }, plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
    colors: ['#00CED1'], dataLabels: { enabled: false }, xaxis: { categories: (rows ?? []).slice(0, 10).map(r => String(r[dim]).slice(0, 28)) },
    tooltip: { y: { formatter: (v: number) => money(v) } },
  },
})
const statusColor: Record<string, string> = { achieved: 'success', on_track: 'info', at_risk: 'warning', behind: 'error', breached: 'error', not_started: 'default' }
const empty = computed(() => !props.kpis.last_sale_date)
</script>

<template>
  <VContainer fluid class="pa-4 pa-sm-6">
    <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-3">
      <div>
        <h3 class="text-h5 font-weight-bold mb-1">Sales overview</h3>
        <p class="text-body-2 text-medium-emphasis mb-0">{{ kpis.from }} to {{ kpis.to }} · data up to {{ kpis.last_sale_date ?? '—' }} · last import {{ when(kpis.last_import_at) }}</p>
      </div>
      <VBtnToggle :model-value="view" mandatory color="primary" variant="outlined" density="comfortable" divided>
        <VBtn v-for="[k, l] in tabs" :key="k" :value="k" @click="go(k)">{{ l }}</VBtn>
      </VBtnToggle>
    </div>

    <FilterBar :url="`/sales/dashboard${view === 'executive' ? '' : '/' + view}`" :filters="filters" :options="options" />

    <VAlert v-if="empty" type="info" variant="tonal" class="mb-4">No sales loaded yet. Import a report in the <a href="/sales/imports">Data Import Centre</a> or set up the mailbox.</VAlert>

    <VRow class="mb-1">
      <VCol cols="6" md="2"><VCard elevation="0" border rounded="lg"><VCardText><div class="text-caption text-medium-emphasis">Net sales (period)</div><div class="text-h5">{{ money(kpis.total) }}</div><div class="text-caption" :class="tone(kpis.yoy_growth)">{{ pct(kpis.yoy_growth) }} vs last year</div></VCardText></VCard></VCol>
      <VCol cols="6" md="2"><VCard elevation="0" border rounded="lg"><VCardText><div class="text-caption text-medium-emphasis">Sales target</div><div class="text-h5">{{ targetSummary?.org_target ? money(targetSummary.org_target) : '—' }}</div><div class="text-caption">{{ targetSummary?.org_achievement ?? '—' }}% achieved</div></VCardText></VCard></VCol>
      <VCol cols="6" md="2"><VCard elevation="0" border rounded="lg"><VCardText><div class="text-caption text-medium-emphasis">Month to date</div><div class="text-h5">{{ money(kpis.mtd) }}</div><div class="text-caption text-medium-emphasis">Month-end estimate {{ money(kpis.month_end_forecast) }}</div></VCardText></VCard></VCol>
      <VCol cols="6" md="2"><VCard elevation="0" border rounded="lg"><VCardText><div class="text-caption text-medium-emphasis">Year to date</div><div class="text-h5">{{ money(kpis.ytd) }}</div><div class="text-caption" :class="tone(kpis.ytd_growth)">{{ pct(kpis.ytd_growth) }} vs {{ money(kpis.previous_ytd) }}</div></VCardText></VCard></VCol>
      <VCol cols="6" md="2"><VCard elevation="0" border rounded="lg"><VCardText><div class="text-caption text-medium-emphasis">Same period last year</div><div class="text-h5">{{ money(kpis.previous_year) }}</div><div class="text-caption">Variance {{ money(kpis.total - kpis.previous_year) }}</div></VCardText></VCard></VCol>
      <VCol cols="6" md="2"><VCard elevation="0" border rounded="lg"><VCardText><div class="text-caption text-medium-emphasis">Active customers / qty</div><div class="text-h5">{{ num(kpis.customers) }}</div><div class="text-caption">{{ num(kpis.quantity) }} units</div></VCardText></VCard></VCol>
    </VRow>

    <template v-if="view === 'executive'">
      <VCard elevation="0" border rounded="lg" class="mb-4" :title="`Sales trend (${trend?.grain})`" subtitle="Compared with the same period last year">
        <VCardText><VueApexCharts type="area" height="300" :options="trendChart.options" :series="trendChart.series" /></VCardText>
      </VCard>
      <VRow>
        <VCol cols="12" md="6"><VCard elevation="0" border rounded="lg" title="Top customers"><VCardText><VueApexCharts type="bar" height="300" v-bind="bar(topCustomers, 'customer')" /></VCardText></VCard></VCol>
        <VCol cols="12" md="6"><VCard elevation="0" border rounded="lg" title="Top products"><VCardText><VueApexCharts type="bar" height="300" v-bind="bar(topProducts, 'product')" /></VCardText></VCard></VCol>
        <VCol v-for="[title, rows, dim] in [['Underperforming customers', decliningCustomers, 'customer'], ['Underperforming products', decliningProducts, 'product']]" :key="String(title)" cols="12" md="6">
          <VCard elevation="0" border rounded="lg" :title="String(title)" subtitle="Largest falls against the same period last year">
            <VTable density="compact"><tbody>
              <tr v-for="r in (rows as any[])" :key="r.key"><td>{{ r[dim as string] }}</td><td class="text-end">{{ money(r.value) }}</td><td class="text-end" :class="tone(r.change)">{{ money(r.change) }}</td><td class="text-end" :class="tone(r.growth)">{{ pct(r.growth) }}</td></tr>
              <tr v-if="!(rows as any[])?.length"><td class="text-medium-emphasis">Nothing to show.</td></tr>
            </tbody></VTable>
          </VCard>
        </VCol>
      </VRow>
    </template>

    <template v-if="view === 'customers' || view === 'products'">
      <VRow>
        <VCol cols="12" md="7">
          <VCard elevation="0" border rounded="lg" :title="view === 'customers' ? 'Customer ranking' : 'Product ranking'">
            <VTable density="compact" fixed-header height="520">
              <thead><tr><th>#</th><th>{{ view === 'customers' ? 'Customer' : 'Product' }}</th><th class="text-end">Net sales</th><th class="text-end">Share</th><th class="text-end">Last year</th><th class="text-end">Growth</th></tr></thead>
              <tbody><tr v-for="(r, i) in ranking" :key="r.key">
                <td>{{ i + 1 }}</td>
                <td><a :href="`/sales/records?${view === 'customers' ? 'customer_id' : 'product_id'}=${r[view === 'customers' ? 'customer_id' : 'product_id']}&from=${kpis.from}&to=${kpis.to}`">{{ r[view === 'customers' ? 'customer' : 'product'] }}</a></td>
                <td class="text-end">{{ money(r.value) }}</td><td class="text-end">{{ r.share }}%</td><td class="text-end">{{ money(r.previous) }}</td><td class="text-end" :class="tone(r.growth)">{{ pct(r.growth) }}</td>
              </tr></tbody>
            </VTable>
          </VCard>
        </VCol>
        <VCol cols="12" md="5">
          <VCard v-if="view === 'customers'" elevation="0" border rounded="lg" class="mb-4" title="Customer concentration">
            <VCardText class="d-flex justify-space-around text-center">
              <div><div class="text-h5">{{ concentration?.top5_share }}%</div><div class="text-caption">of sales from the top 5</div></div>
              <div><div class="text-h5">{{ concentration?.top10_share }}%</div><div class="text-caption">from the top 10</div></div>
              <div><div class="text-h5">{{ concentration?.customers }}</div><div class="text-caption">buying customers</div></div>
            </VCardText>
          </VCard>
          <VCard elevation="0" border rounded="lg" class="mb-4" :title="view === 'customers' ? 'By customer group' : 'By category'">
            <VCardText><VueApexCharts type="bar" height="260" v-bind="view === 'customers' ? bar(groups, 'customer_group') : bar(categories, 'category')" /></VCardText>
          </VCard>
          <VCard elevation="0" border rounded="lg" :title="view === 'customers' ? 'By region' : 'By brand'">
            <VCardText><VueApexCharts type="bar" height="260" v-bind="view === 'customers' ? bar(regions, 'region') : bar(brands, 'brand')" /></VCardText>
          </VCard>
        </VCol>
      </VRow>
    </template>

    <template v-if="view === 'targets'">
      <VRow class="mb-1">
        <VCol v-for="k in [['Targets in period', targetSummary?.count], ['Achieved', targetSummary?.achieved], ['At risk or behind', targetSummary?.at_risk], ['Remaining (organisation)', money(targetSummary?.org_remaining)], ['Forecast (organisation)', money(targetSummary?.org_forecast)]]" :key="k[0]" cols="6" md="">
          <VCard elevation="0" border rounded="lg"><VCardText><div class="text-caption text-medium-emphasis">{{ k[0] }}</div><div class="text-h5">{{ k[1] }}</div></VCardText></VCard>
        </VCol>
      </VRow>
      <VCard elevation="0" border rounded="lg">
        <VTable density="compact">
          <thead><tr><th>Target</th><th>Period</th><th class="text-end">Target</th><th class="text-end">Actual</th><th style="width: 18%">Achievement</th><th class="text-end">Remaining</th><th class="text-end">Needed / day</th><th class="text-end">Forecast</th><th>Status</th></tr></thead>
          <tbody>
            <tr v-for="t in targets" :key="t.uuid" class="cursor-pointer" @click="router.get(`/sales/targets/${t.uuid}`)">
              <td>{{ t.name }}</td><td class="text-caption">{{ t.start_date }} → {{ t.end_date }}</td>
              <td class="text-end">{{ t.method === 'growth' ? t.performance.target + '%' : t.metric.includes('value') ? money(t.performance.target) : num(t.performance.target) }}</td>
              <td class="text-end">{{ t.method === 'growth' ? t.performance.actual + '%' : t.metric.includes('value') ? money(t.performance.actual) : num(t.performance.actual) }}</td>
              <td><VProgressLinear :model-value="Math.min(100, t.performance.achievement ?? 0)" :color="statusColor[t.performance.status]" height="16" rounded><small>{{ t.performance.achievement ?? '—' }}%</small></VProgressLinear></td>
              <td class="text-end">{{ num(t.performance.remaining) }}</td><td class="text-end">{{ t.performance.required_daily === null ? '—' : num(t.performance.required_daily) }}</td>
              <td class="text-end">{{ num(t.performance.forecast) }}</td>
              <td><VChip size="x-small" :color="statusColor[t.performance.status]" variant="tonal">{{ t.performance.status.replace('_', ' ') }}</VChip></td>
            </tr>
            <tr v-if="!targets?.length"><td colspan="9" class="text-medium-emphasis">No approved targets in this period. <a href="/sales/targets">Create one</a>.</td></tr>
          </tbody>
        </VTable>
      </VCard>
    </template>
  </VContainer>
</template>
