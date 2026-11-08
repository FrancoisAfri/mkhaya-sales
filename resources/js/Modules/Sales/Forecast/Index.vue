<script setup lang="ts">
import { computed } from 'vue'
import VueApexCharts from 'vue3-apexcharts'
import FilterBar from '@/Components/Sales/FilterBar.vue'

const props = defineProps<{ forecast: any; products: any[]; filters: Record<string, any>; options: Record<string, any[]> }>()
const money = (n: any) => 'R ' + Number(n ?? 0).toLocaleString('en-ZA', { maximumFractionDigits: 0 })
const chart = computed(() => {
  const hist = props.forecast.history.slice(-12)
  const months = [...hist.map((h: any) => h.month), ...props.forecast.months.slice(1).map((m: any) => m.month)]
  const actual = months.map(m => hist.find((h: any) => h.month === m)?.value ?? null)
  const fc = months.map(m => props.forecast.months.find((x: any) => x.month === m)?.forecast ?? null)
  return {
    series: [{ name: 'Actual', type: 'column', data: actual }, { name: 'Forecast (estimate)', type: 'line', data: fc }],
    options: { chart: { toolbar: { show: false }, fontFamily: 'inherit' }, colors: ['#6A0DAD', '#E4C746'], stroke: { width: [0, 3], dashArray: [0, 6] },
      xaxis: { categories: months }, dataLabels: { enabled: false }, tooltip: { y: { formatter: (v: number) => v === null ? '—' : money(v) } },
      yaxis: { labels: { formatter: (v: number) => 'R ' + (v / 1000).toFixed(0) + 'k' } } },
  }
})
</script>

<template>
  <VContainer fluid class="pa-4 pa-sm-6">
    <h3 class="text-h5 font-weight-bold mb-1">Sales forecast</h3>
    <p class="text-body-2 text-medium-emphasis mb-3">{{ forecast.basis }}</p>
    <FilterBar url="/sales/forecast" :filters="filters" :options="options" />
    <VAlert type="info" variant="tonal" density="compact" class="mb-4">Forecasts are estimates from past sales, not guaranteed outcomes. Recent growth vs last year: {{ forecast.recent_growth === null ? 'not enough history' : forecast.recent_growth + '%' }}.</VAlert>
    <VCard elevation="0" border rounded="lg" class="mb-4"><VCardText><VueApexCharts type="line" height="320" :options="chart.options" :series="chart.series" /></VCardText></VCard>
    <VRow>
      <VCol cols="12" md="7">
        <VCard elevation="0" border rounded="lg" title="Projection by month">
          <VTable density="compact">
            <thead><tr><th>Month</th><th class="text-end">Actual so far</th><th class="text-end">Forecast</th><th class="text-end">Likely range</th><th class="text-end">Last year</th><th>How</th></tr></thead>
            <tbody><tr v-for="m in forecast.months" :key="m.month"><td>{{ m.month }}</td><td class="text-end">{{ m.actual === null ? '—' : money(m.actual) }}</td><td class="text-end font-weight-bold">{{ money(m.forecast) }}</td>
              <td class="text-end text-caption">{{ money(m.low) }} – {{ money(m.high) }}</td><td class="text-end">{{ m.last_year ? money(m.last_year) : '—' }}</td><td class="text-caption">{{ m.method }}</td></tr></tbody>
          </VTable>
        </VCard>
      </VCol>
      <VCol cols="12" md="5">
        <VCard elevation="0" border rounded="lg" title="Product momentum" subtitle="Last 3 months vs same months last year">
          <VTable density="compact"><tbody><tr v-for="p in products" :key="p.key"><td>{{ p.product }}</td><td class="text-end">{{ money(p.value) }}</td><td class="text-end" :class="(p.growth ?? 0) >= 0 ? 'text-success' : 'text-error'">{{ p.growth === null ? 'new' : p.growth + '%' }}</td></tr></tbody></VTable>
        </VCard>
      </VCol>
    </VRow>
  </VContainer>
</template>
