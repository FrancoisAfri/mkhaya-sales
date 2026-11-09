<script setup lang="ts">
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import VChart from 'vue-echarts'
import 'echarts'
import FilterBar from '@/Components/Sales/FilterBar.vue'
import { forecastCombo, fmt, GOOD, AMBER, BAD } from '@/Components/BI/visuals'

const props = defineProps<{
  forecast: any; horizon: number; measure: 'value' | 'cases'; targets: Record<string, number | null>
  categories: any[]; products: any[]; filters: Record<string, any>; options: Record<string, any[]>
}>()

// ── Controls: value or cases, and the forecast period (1–18 months or custom) ──
const isCases = computed(() => props.measure === 'cases')
const presets = [1, 2, 3, 6, 9, 12, 18]
const choice = ref<number | 'custom'>(presets.includes(props.horizon) ? props.horizon : 'custom')
const custom = ref<number>(props.horizon)
const periodItems = [...presets.map(n => ({ value: n, title: n === 1 ? '1 month' : `${n} months` })), { value: 'custom', title: 'Custom…' }]
const go = (q: Record<string, any>) => router.get('/sales/forecast', { ...props.filters, months: props.horizon, measure: props.measure, ...q }, { preserveScroll: true, preserveState: true })
function setHorizon(n: number) { if (n >= 1 && n <= 36) go({ months: n }) }
function onChoice(v: number | 'custom') { choice.value = v; if (v !== 'custom') setHorizon(v) }

// ── Formatting: rand or cases ──
const full = (n: any) => n === null || n === undefined ? '—' : isCases.value ? `${Number(n).toLocaleString('en-ZA', { maximumFractionDigits: 0 })} cases` : 'R ' + Number(n).toLocaleString('en-ZA', { maximumFractionDigits: 0 })
const short = (n: any) => n === null || n === undefined ? '—' : isCases.value ? `${fmt(n, 'number')} cases` : fmt(n, 'currency')
const pct = (n: any) => n === null || n === undefined ? '—' : `${n > 0 ? '+' : ''}${Number(n).toFixed(1)}%`
const monthName = (m: string) => new Date(`${m}-01T00:00:00`).toLocaleDateString('en-ZA', { month: 'short', year: 'numeric' })
const tone = (n: any) => (n === null || n === undefined ? undefined : n >= 0 ? GOOD : BAD)

const w = computed(() => props.forecast.workings)
const cur = computed(() => props.forecast.months[0])
const ahead = computed(() => props.forecast.months.slice(1))
const curTarget = computed(() => props.targets[cur.value.month] ?? null)
const vsTarget = computed(() => (curTarget.value ? cur.value.forecast / curTarget.value * 100 : null))
const aheadTotal = computed(() => ahead.value.reduce((a: number, m: any) => a + (m.forecast ?? 0), 0))
const aheadLy = computed(() => ahead.value.reduce((a: number, m: any) => a + (m.last_year ?? 0), 0))
const aheadGrowth = computed(() => (aheadLy.value ? (aheadTotal.value - aheadLy.value) / aheadLy.value * 100 : null))
const acc = computed(() => props.forecast.accuracy)

// ── The worked example for "next month" (the first month after this one) ──
const nextM = computed(() => ahead.value[0] ?? null)
const g = computed(() => w.value.growth_months)
const gNow = computed(() => g.value.reduce((a: number, x: any) => a + x.value, 0))
const gPrev = computed(() => g.value.reduce((a: number, x: any) => a + x.last_year, 0))
const wmaParts = computed(() => (w.value.wma_months ?? []).map((x: any, i: number, all: any[]) => ({ ...x, weight: i + 1 + (3 - all.length) })))

const chart = computed(() => {
  const hist = props.forecast.history.slice(-12)
  const months: string[] = [...hist.map((h: any) => h.month), ...ahead.value.map((m: any) => m.month)]
  const at = (m: string) => props.forecast.months.find((x: any) => x.month === m)
  return forecastCombo(months.map(monthName), months.map(m => hist.find((h: any) => h.month === m)?.value ?? null), months.map(m => at(m)?.forecast ?? null),
    months.map(m => at(m)?.low ?? null), months.map(m => at(m)?.high ?? null), months.map(m => props.targets[m] ?? null), isCases.value ? 'number' : 'currency')
})
const accChart = computed(() => ({
  grid: { left: 8, right: 8, top: 28, bottom: 4, containLabel: true }, legend: { top: 0, right: 0, icon: 'circle', itemWidth: 8, textStyle: { fontSize: 11, color: '#6B6B7B' } },
  tooltip: { trigger: 'axis', valueFormatter: (v: number) => full(v) },
  xAxis: { type: 'category', data: acc.value.months.map((m: any) => monthName(m.month)), axisTick: { show: false }, axisLine: { lineStyle: { color: '#E6E6EE' } }, axisLabel: { color: '#5B5B6E', fontSize: 11 } },
  yAxis: { type: 'value', splitLine: { lineStyle: { color: '#F1F1F5' } }, axisLabel: { color: '#8A8A99', fontSize: 10, formatter: (v: number) => short(v).replace(' cases', '') } },
  series: [{ name: 'Actual', type: 'bar', barMaxWidth: 18, data: acc.value.months.map((m: any) => m.actual), itemStyle: { color: '#6A0DAD', borderRadius: [5, 5, 0, 0] } },
    { name: 'What the method forecast', type: 'bar', barMaxWidth: 18, data: acc.value.months.map((m: any) => m.forecast), itemStyle: { color: '#E4C746', borderRadius: [5, 5, 0, 0] } }],
}))
</script>

<template>
  <VContainer fluid class="pa-4 pa-sm-6">
    <header class="fc-head">
      <div>
        <p class="fc-eyebrow">Sales & Reports</p>
        <h1 class="fc-title">Sales forecast</h1>
        <p class="fc-sub">{{ forecast.basis }}</p>
      </div>
      <div class="d-flex align-center flex-wrap gap-3">
        <div class="fc-seg">
          <button :class="{ on: !isCases }" @click="go({ measure: 'value' })"><VIcon icon="tabler-currency-dollar" size="15" /> Value (R)</button>
          <button :class="{ on: isCases }" @click="go({ measure: 'cases' })"><VIcon icon="tabler-package" size="15" /> Volume (cases)</button>
        </div>
        <VSelect :model-value="choice" :items="periodItems" label="Forecast period" density="compact" variant="outlined" hide-details style="width: 170px" @update:model-value="onChoice" />
        <VTextField v-if="choice === 'custom'" v-model.number="custom" type="number" min="1" max="36" label="Months (1–36)" density="compact" variant="outlined" hide-details style="width: 130px" @keyup.enter="setHorizon(custom)" @blur="setHorizon(custom)" />
      </div>
    </header>

    <FilterBar :url="`/sales/forecast?months=${horizon}&measure=${measure}`" :filters="filters" :options="options" />

    <!-- Headline numbers -->
    <div class="fc-kpis">
      <div class="fc-kpi">
        <small>{{ monthName(cur.month) }} — month-end estimate</small>
        <strong>{{ short(cur.forecast) }}</strong>
        <div class="fc-bar"><span :style="{ width: Math.min(100, vsTarget ?? 0) + '%', background: (vsTarget ?? 0) >= 100 ? GOOD : (vsTarget ?? 0) >= 90 ? AMBER : BAD }" /></div>
        <p v-if="curTarget">Target {{ short(curTarget) }} · <b :style="{ color: (vsTarget ?? 0) >= 100 ? GOOD : BAD }">{{ Math.round(vsTarget ?? 0) }}% of target</b></p>
        <p v-else>No approved {{ isCases ? 'cases' : 'value' }} target this month</p>
      </div>
      <div class="fc-kpi">
        <small>Next {{ ahead.length }} {{ ahead.length === 1 ? 'month' : 'months' }} — forecast</small>
        <strong>{{ short(aheadTotal) }}</strong>
        <p>Same months last year {{ short(aheadLy) }} · <b :style="{ color: tone(aheadGrowth) }">{{ pct(aheadGrowth) }}</b></p>
      </div>
      <div class="fc-kpi">
        <small>Recent trend</small>
        <strong :style="{ color: tone(forecast.recent_growth) }"><VIcon :icon="(forecast.recent_growth ?? 0) >= 0 ? 'tabler-trending-up' : 'tabler-trending-down'" size="26" /> {{ pct(forecast.recent_growth) }}</strong>
        <p>{{ g.length ? `${monthName(g[0].month)} – ${monthName(g[g.length - 1].month)} vs the same months last year` : 'Not enough history yet' }}</p>
      </div>
      <div class="fc-kpi">
        <small>How accurate the method is</small>
        <strong>{{ acc.accuracy === null ? '—' : acc.accuracy + '%' }}</strong>
        <p v-if="acc.mape !== null">On average {{ acc.mape }}% away from what was actually sold, over the last {{ acc.months.length }} months</p>
        <p v-else>Needs at least 13 months of history</p>
      </div>
    </div>

    <!-- Chart -->
    <VCard class="mb-4">
      <div class="fc-card-head"><div><h3>Actual and forecast {{ isCases ? 'volume' : 'sales' }}</h3><span>The last 12 months as bars, then the forecast with its likely range. The dotted green line is the target.</span></div></div>
      <VChart :option="chart" autoresize style="height: 340px" class="px-3 pb-3" />
    </VCard>

    <!-- How it is calculated -->
    <VCard class="mb-4">
      <div class="fc-card-head"><div><h3><VIcon icon="tabler-math-function" size="20" color="primary" class="me-1" />How the forecast is calculated</h3>
        <span>Three plain methods, no black box. Every figure below is today's real number{{ isCases ? ' in cases' : '' }}, so anyone can check it with a calculator.</span></div></div>
      <div class="fc-steps">
        <section class="fc-step">
          <header><span>1</span><div><h4>This month: run rate</h4><p>Used for {{ monthName(cur.month) }}, the month in progress.</p></div></header>
          <div class="fc-formula">Month-end estimate = sold so far ÷ days gone × days in the month</div>
          <div class="fc-example">
            <div>{{ full(w.month_to_date) }} sold in {{ w.days_elapsed }} days ({{ full(w.daily_rate) }} a day)</div>
            <div>{{ full(w.month_to_date) }} ÷ {{ w.days_elapsed }} × {{ w.days_in_month }} = <b>{{ full(w.run_rate) }}</b></div>
          </div>
          <p class="fc-note">Calendar days are used, so a quiet weekend early in the month pulls the estimate down until the week catches up.</p>
        </section>

        <section class="fc-step">
          <header><span>2</span><div><h4>Coming months: last year × recent trend</h4><p>Keeps the season (festive peak, quiet January) and adjusts it by how this year is going.</p></div></header>
          <div class="fc-formula">Forecast = same month last year × (1 + recent growth)</div>
          <div class="fc-example">
            <div class="mb-1">Recent growth = last 3 finished months against the same 3 months a year earlier:</div>
            <table class="fc-mini"><tr v-for="x in g" :key="x.month"><td>{{ monthName(x.month) }}</td><td>{{ full(x.value) }}</td><td class="m">vs {{ monthName(x.last_year_month) }}</td><td>{{ full(x.last_year) }}</td></tr>
              <tr class="t"><td>Total</td><td>{{ full(gNow) }}</td><td /><td>{{ full(gPrev) }}</td></tr></table>
            <div>({{ full(gNow) }} − {{ full(gPrev) }}) ÷ {{ full(gPrev) }} = <b>{{ pct(w.growth) }}</b></div>
            <div v-if="nextM && nextM.last_year" class="mt-1">{{ monthName(nextM.month) }}: {{ full(nextM.last_year) }} × (1 {{ (w.growth ?? 0) < 0 ? '−' : '+' }} {{ Math.abs((w.growth ?? 0) / 100).toFixed(3) }}) = <b>{{ full(nextM.forecast) }}</b></div>
          </div>
          <p class="fc-note">More than 12 months ahead there is no real "last year" yet, so the forecast made for that month a year earlier is grown by the same trend.</p>
        </section>

        <section class="fc-step">
          <header><span>3</span><div><h4>Back-up: weighted average</h4><p>Only when a month has no sales last year (new product, new customer).</p></div></header>
          <div class="fc-formula">Forecast = (1 × three months ago + 2 × two months ago + 3 × last month) ÷ 6</div>
          <div class="fc-example">
            <div v-if="wmaParts.length">({{ wmaParts.map((x: any) => `${x.weight} × ${full(x.value)}`).join(' + ') }}) ÷ {{ wmaParts.reduce((a: number, x: any) => a + x.weight, 0) }} = <b>{{ full(w.wma) }}</b></div>
            <div v-else>Not enough months yet.</div>
          </div>
          <p class="fc-note">Recent months count more, so the average follows the latest direction.</p>
        </section>

        <section class="fc-step">
          <header><span><VIcon icon="tabler-arrows-vertical" size="16" /></span><div><h4>The likely range (shaded band)</h4><p>How far the real result could reasonably land from the forecast.</p></div></header>
          <div class="fc-formula">Range = forecast ± the average month-to-month change over the last 12 months</div>
          <div class="fc-example">
            <div>Average change: <b>±{{ w.spread }}%</b> (kept between 5% and 40%)</div>
            <div v-if="nextM">{{ monthName(nextM.month) }}: {{ full(nextM.low) }} to {{ full(nextM.high) }}</div>
          </div>
          <p class="fc-note">A wide band means sales swing a lot from month to month; plan for the low end.</p>
        </section>
      </div>
    </VCard>

    <VRow>
      <VCol cols="12" lg="7">
        <VCard class="h-100">
          <div class="fc-card-head"><div><h3>Forecast by category</h3><span>The same methods, run for each category on its own.</span></div></div>
          <VTable density="comfortable" class="fc-table">
            <thead><tr><th>Category</th><th class="text-end">{{ monthName(cur.month) }} so far</th><th class="text-end">Month-end estimate</th><th class="text-end">{{ nextM ? monthName(nextM.month) : 'Next month' }}</th><th class="text-end">vs last year</th><th class="text-end">Trend</th></tr></thead>
            <tbody>
              <tr v-for="c in categories" :key="c.category"><td class="font-weight-medium">{{ c.category }}</td><td class="text-end">{{ full(c.month_to_date) }}</td><td class="text-end font-weight-bold">{{ full(c.month_end) }}</td>
                <td class="text-end">{{ full(c.next) }}</td><td class="text-end text-medium-emphasis">{{ full(c.next_last_year) }}</td><td class="text-end" :style="{ color: tone(c.growth) }">{{ pct(c.growth) }}</td></tr>
              <tr v-if="!categories.length"><td colspan="6" class="text-medium-emphasis">No category sales in the last three months.</td></tr>
            </tbody>
          </VTable>
        </VCard>
      </VCol>
      <VCol cols="12" lg="5">
        <VCard class="h-100">
          <div class="fc-card-head"><div><h3>Accuracy check</h3><span>Each past month forecast the same way, then compared with what was really sold.</span></div></div>
          <template v-if="acc.months.length">
            <VChart :option="accChart" autoresize style="height: 190px" class="px-3" />
            <div class="fc-acc">
              <span v-for="m in acc.months" :key="m.month"><small>{{ monthName(m.month) }}</small><b :style="{ color: Math.abs(m.error_pct) <= 10 ? GOOD : Math.abs(m.error_pct) <= 20 ? AMBER : BAD }">{{ pct(m.error_pct) }}</b></span>
            </div>
          </template>
          <p v-else class="pa-5 text-medium-emphasis">Needs at least 13 months of sales history.</p>
        </VCard>
      </VCol>
    </VRow>

    <VCard class="fc-proj mt-4">
      <div class="fc-card-head"><div><h3>Projection by month</h3><span>Which method was used for each month.</span></div></div>
      <VTable density="comfortable">
        <thead><tr><th>Month</th><th class="text-end">Actual so far</th><th class="text-end">Forecast</th><th class="text-end">Likely range</th><th class="text-end">Last year</th><th class="text-end">Target</th><th>Method</th></tr></thead>
        <tbody><tr v-for="m in forecast.months" :key="m.month"><td class="font-weight-medium">{{ monthName(m.month) }}</td><td class="text-end">{{ m.actual === null ? '—' : full(m.actual) }}</td><td class="text-end font-weight-bold">{{ full(m.forecast) }}</td>
          <td class="text-end text-medium-emphasis">{{ short(m.low) }} – {{ short(m.high) }}</td><td class="text-end">{{ m.last_year ? full(m.last_year) : '—' }}</td><td class="text-end">{{ full(targets[m.month]) }}</td>
          <td><span class="fc-method">{{ m.method }}</span></td></tr></tbody>
      </VTable>
    </VCard>

    <VCard class="mt-4">
      <div class="fc-card-head"><div><h3>Product momentum</h3><span>Last 3 months against the same months last year — the products driving the trend.</span></div></div>
      <div class="fc-momentum">
        <div v-for="p in products" :key="p.key" class="fc-mom"><span class="fc-mom__name" :title="p.product">{{ p.product }}</span><span class="fc-mom__val">{{ full(p.value) }}</span>
          <span class="fc-pill" :style="{ color: tone(p.growth), background: (p.growth ?? 0) >= 0 ? '#EAF5EC' : '#FBECEC' }">{{ p.growth === null ? 'new' : pct(p.growth) }}</span></div>
      </div>
    </VCard>

    <p class="text-caption text-medium-emphasis mt-4">Forecasts are estimates from past sales, not guaranteed outcomes. Filters above apply to every number on this page.</p>
  </VContainer>
</template>

<style scoped>
.fc-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
.fc-eyebrow { margin: 0 0 4px; font-size: 13px; font-weight: 600; color: #6A0DAD; }
.fc-title { margin: 0; font-size: 28px; font-weight: 700; letter-spacing: -.6px; color: #16161F; }
.fc-sub { margin: 4px 0 0; font-size: 13px; color: #8A8A99; }
.fc-seg { display: inline-flex; background: #F3F1F8; border-radius: 10px; padding: 3px; }
.fc-seg button { border: 0; background: transparent; padding: 6px 12px; font-size: 13px; font-weight: 600; color: #5B5B6E; border-radius: 8px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; }
.fc-seg button.on { background: #fff; color: #6A0DAD; box-shadow: 0 1px 2px rgba(17,17,26,.08); }
.fc-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 16px; margin: 16px 0; }
.fc-kpi { background: #fff; border: 1px solid #EEEEF3; border-radius: 18px; padding: 18px 20px; }
.fc-kpi small { font-size: 13px; font-weight: 600; color: #3A3A4A; }
.fc-kpi strong { display: flex; align-items: center; gap: 4px; font-family: Montserrat, sans-serif; font-size: 28px; font-weight: 700; letter-spacing: -.5px; color: #16161F; margin: 6px 0 4px; }
.fc-kpi p { margin: 6px 0 0; font-size: 12px; color: #77778A; line-height: 1.4; }
.fc-bar { height: 8px; background: #F1EDF7; border-radius: 99px; overflow: hidden; } .fc-bar span { display: block; height: 100%; border-radius: 99px; }
.fc-card-head { padding: 18px 20px 8px; } .fc-card-head h3 { margin: 0; font-size: 16px; font-weight: 700; color: #16161F; display: flex; align-items: center; } .fc-card-head span { font-size: 12.5px; color: #77778A; }
.fc-steps { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; padding: 8px 20px 20px; }
@media (max-width: 900px) { .fc-steps { grid-template-columns: 1fr; } }
.fc-step { border: 1px solid #EEEEF3; border-radius: 16px; padding: 16px; background: #FCFBFE; }
.fc-step header { display: flex; gap: 12px; margin-bottom: 12px; }
.fc-step header > span { flex: 0 0 30px; height: 30px; border-radius: 10px; background: #F3EEFA; color: #6A0DAD; font-weight: 800; display: flex; align-items: center; justify-content: center; }
.fc-step h4 { margin: 0; font-size: 14.5px; font-weight: 700; color: #16161F; } .fc-step header p { margin: 2px 0 0; font-size: 12px; color: #77778A; }
.fc-formula { background: #fff; border: 1px dashed #D9C8EE; border-radius: 10px; padding: 9px 12px; font-size: 13px; font-weight: 700; color: #6A0DAD; margin-bottom: 10px; }
.fc-example { font-size: 13px; color: #2B2B38; line-height: 1.6; } .fc-example b { color: #16161F; }
.fc-note { margin: 10px 0 0; font-size: 12px; color: #8A8A99; }
.fc-mini { border-collapse: collapse; margin: 2px 0 6px; font-size: 12px; } .fc-mini td { padding: 1px 10px 1px 0; } .fc-mini td.m { color: #8A8A99; } .fc-mini tr.t td { border-top: 1px solid #EEEEF3; font-weight: 700; }
.fc-acc { display: grid; grid-template-columns: repeat(6, 1fr); gap: 6px; padding: 6px 20px 18px; text-align: center; }
.fc-acc small { display: block; font-size: 10.5px; color: #8A8A99; } .fc-acc b { font-size: 13px; }
.fc-pill { font-size: 12px; font-weight: 700; border-radius: 8px; padding: 2px 8px; }
.fc-table :deep(td), .fc-table :deep(th) { white-space: nowrap; }
.fc-proj :deep(td), .fc-proj :deep(th) { white-space: nowrap; }
.fc-method { display: inline-block; font-size: 12px; color: #5B5B6E; background: #F6F4FA; border-radius: 8px; padding: 2px 8px; white-space: nowrap; }
.fc-momentum { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 2px 24px; padding: 4px 20px 18px; }
.fc-mom { display: flex; align-items: center; gap: 10px; padding: 7px 0; border-bottom: 1px solid #F4F4F7; font-size: 13px; }
.fc-mom__name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #2B2B38; }
.fc-mom__val { color: #5B5B6E; white-space: nowrap; }
</style>
