<script setup lang="ts">
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import VChart from 'vue-echarts'
import 'echarts'
import FilterBar from '@/Components/Sales/FilterBar.vue'
import { forecastCombo } from '@/Components/BI/visuals'

const props = defineProps<{ forecast: any; horizon: number; products: any[]; filters: Record<string, any>; options: Record<string, any[]> }>()
// Forecast period (meeting 2026-11-09): fixed choices or a custom number of months.
const presets = [1, 2, 3, 6, 9, 12, 18]
const choice = ref<number | 'custom'>(presets.includes(props.horizon) ? props.horizon : 'custom')
const custom = ref<number>(props.horizon)
const periodItems = [...presets.map(n => ({ value: n, title: n === 1 ? '1 month' : `${n} months` })), { value: 'custom', title: 'Custom…' }]
function setHorizon(n: number) { if (n >= 1 && n <= 36) router.get('/sales/forecast', { ...props.filters, months: n }, { preserveScroll: true, preserveState: true }) }
function onChoice(v: number | 'custom') { choice.value = v; if (v !== 'custom') setHorizon(v) }
const money = (n: any) => 'R ' + Number(n ?? 0).toLocaleString('en-ZA', { maximumFractionDigits: 0 })
const chart = computed(() => {
  const hist = props.forecast.history.slice(-12)
  const months: string[] = [...hist.map((h: any) => h.month), ...props.forecast.months.slice(1).map((m: any) => m.month)]
  const at = (m: string) => props.forecast.months.find((x: any) => x.month === m)
  return forecastCombo(months, months.map(m => hist.find((h: any) => h.month === m)?.value ?? null), months.map(m => at(m)?.forecast ?? null),
    months.map(m => at(m)?.low ?? null), months.map(m => at(m)?.high ?? null))
})
</script>

<template>
  <VContainer fluid class="pa-4 pa-sm-6">
    <div class="d-flex flex-wrap align-center justify-space-between gap-3">
      <h3 class="text-h5 font-weight-bold mb-1">Sales forecast</h3>
      <div class="d-flex align-center gap-2">
        <VSelect :model-value="choice" :items="periodItems" label="Forecast period" density="compact" variant="outlined" hide-details style="width: 180px" @update:model-value="onChoice" />
        <VTextField v-if="choice === 'custom'" v-model.number="custom" type="number" min="1" max="36" label="Months (1–36)" density="compact" variant="outlined" hide-details style="width: 140px" @keyup.enter="setHorizon(custom)" @blur="setHorizon(custom)" />
      </div>
    </div>
    <p class="text-body-2 text-medium-emphasis mb-3">{{ forecast.basis }}</p>
    <FilterBar :url="`/sales/forecast?months=${horizon}`" :filters="filters" :options="options" />
    <VAlert type="info" variant="tonal" density="compact" class="mb-4">Forecasts are estimates from past sales, not guaranteed outcomes. Recent growth vs last year: {{ forecast.recent_growth === null ? 'not enough history' : forecast.recent_growth + '%' }}.</VAlert>
    <VCard class="mb-4"><VCardText><VChart :option="chart" autoresize style="height: 320px" /></VCardText></VCard>
    <VRow>
      <VCol cols="12" md="7">
        <VCard title="Projection by month" class="fc-proj">
          <VTable density="compact">
            <thead><tr><th>Month</th><th class="text-end">Actual so far</th><th class="text-end">Forecast</th><th class="text-end">Likely range</th><th class="text-end">Last year</th><th>How</th></tr></thead>
            <tbody><tr v-for="m in forecast.months" :key="m.month"><td>{{ m.month }}</td><td class="text-end">{{ m.actual === null ? '—' : money(m.actual) }}</td><td class="text-end font-weight-bold">{{ money(m.forecast) }}</td>
              <td class="text-end text-caption">{{ money(m.low) }} – {{ money(m.high) }}</td><td class="text-end">{{ m.last_year ? money(m.last_year) : '—' }}</td><td class="text-caption">{{ m.method }}</td></tr></tbody>
          </VTable>
        </VCard>
      </VCol>
      <VCol cols="12" md="5">
        <VCard title="Product momentum" subtitle="Last 3 months vs same months last year">
          <VTable density="compact"><tbody><tr v-for="p in products" :key="p.key"><td>{{ p.product }}</td><td class="text-end">{{ money(p.value) }}</td><td class="text-end" :class="(p.growth ?? 0) >= 0 ? 'text-success' : 'text-error'">{{ p.growth === null ? 'new' : p.growth + '%' }}</td></tr></tbody></VTable>
        </VCard>
      </VCol>
    </VRow>
  </VContainer>
</template>

<style scoped>
.fc-proj :deep(td), .fc-proj :deep(th) { white-space: nowrap; }
.fc-proj :deep(td:last-child) { white-space: normal; min-width: 220px; }
</style>
