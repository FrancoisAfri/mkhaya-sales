<script setup lang="ts">
// Sales beside stock, promotions and prices (gap 7).
import FilterBar from '@/Components/Sales/FilterBar.vue'

defineProps<{ snapshot: string | null; stock: any[]; promotions: any[]; prices: any[]; asOf: string; filters: Record<string, any>; options: Record<string, any[]> }>()
const n = (v: any) => (v === null || v === undefined ? '—' : Number(v).toLocaleString('en-ZA', { maximumFractionDigits: 0 }))
const r = (v: any) => (v === null || v === undefined ? '—' : 'R ' + Number(v).toLocaleString('en-ZA', { maximumFractionDigits: 2 }))
const coverTone = (d: number | null) => (d === null ? 'default' : d < 7 ? 'error' : d < 14 ? 'warning' : d > 60 ? 'info' : 'success')
const coverText = (d: number | null) => (d === null ? 'not selling' : d < 7 ? 'runs out soon' : d < 14 ? 'watch' : d > 60 ? 'overstocked' : 'healthy')
const tone = (v: number | null) => (v === null ? undefined : v >= 0 ? '#2E7D32' : '#C62828')
</script>

<template>
  <VContainer fluid class="pa-4 pa-sm-6">
    <header class="tr-head">
      <div><p class="tr-eyebrow">Products & Customers</p><h1 class="tr-title">Stock, promotions & prices</h1>
        <p class="tr-sub">Sales up to {{ asOf }} set against the stock, promotions and price lists you load in the Import Centre.</p></div>
      <VBtn variant="tonal" prepend-icon="tabler-file-import" href="/sales/imports">Load stock, promotions or prices</VBtn>
    </header>
    <FilterBar url="/sales/trade" :filters="filters" :options="options" />

    <VCard class="mb-4">
      <div class="tr-card-head"><div><h3>Stock cover</h3><span>{{ snapshot ? `Stock on ${snapshot} ÷ average cases sold a day over the last 28 days.` : 'No stock loaded yet.' }}</span></div></div>
      <VTable v-if="stock.length" density="comfortable" hover>
        <thead><tr><th>Product</th><th class="text-end">Cases on hand</th><th class="text-end">Sold a day</th><th class="text-end">Days of cover</th><th /></tr></thead>
        <tbody><tr v-for="s in stock" :key="s.product"><td>{{ s.product }}</td><td class="text-end">{{ n(s.on_hand) }}</td><td class="text-end">{{ s.daily }}</td>
          <td class="text-end font-weight-bold">{{ s.cover_days ?? '—' }}</td><td class="text-end"><VChip size="small" variant="tonal" :color="coverTone(s.cover_days)">{{ coverText(s.cover_days) }}</VChip></td></tr></tbody>
      </VTable>
      <p v-else class="tr-empty">Load a stock-on-hand file (product, cases on hand, optional DC) to see how many days each product will last.</p>
    </VCard>

    <VCard class="mb-4">
      <div class="tr-card-head"><div><h3>Promotion uplift</h3><span>Cases sold during each promotion against the same number of days just before it.</span></div></div>
      <VTable v-if="promotions.length" density="comfortable" hover>
        <thead><tr><th>Promotion</th><th>Covers</th><th>Dates</th><th class="text-end">Cases during</th><th class="text-end">Cases before</th><th class="text-end">Uplift</th><th class="text-end">Value during</th></tr></thead>
        <tbody><tr v-for="p in promotions" :key="p.name + p.start"><td class="font-weight-medium">{{ p.name }} <VChip v-if="p.running" size="x-small" color="primary" variant="tonal">running</VChip>
          <div class="text-caption text-medium-emphasis">{{ [p.mechanic, p.discount_pct ? p.discount_pct + '% off' : null].filter(Boolean).join(' · ') }}</div></td>
          <td class="text-caption">{{ p.covers }}</td><td class="text-caption">{{ p.start }} → {{ p.end }} ({{ p.days }} days)</td><td class="text-end">{{ n(p.cases) }}</td><td class="text-end">{{ n(p.cases_before) }}</td>
          <td class="text-end font-weight-bold" :style="{ color: tone(p.uplift) }">{{ p.uplift === null ? '—' : (p.uplift > 0 ? '+' : '') + p.uplift + '%' }}</td><td class="text-end">{{ r(p.value) }}</td></tr></tbody>
      </VTable>
      <p v-else class="tr-empty">Load promotions (name, dates, and the product, category, customer or group they cover) to see the uplift of each.</p>
    </VCard>

    <VCard>
      <div class="tr-card-head"><div><h3>Price realisation</h3><span>Price achieved per case over the last 30 days against the price list.</span></div></div>
      <VTable v-if="prices.length" density="comfortable" hover>
        <thead><tr><th>Product</th><th class="text-end">List price / case</th><th class="text-end">Achieved / case</th><th class="text-end">Realisation</th><th class="text-end">Cases</th></tr></thead>
        <tbody><tr v-for="p in prices" :key="p.product"><td>{{ p.product }}</td><td class="text-end">{{ r(p.list) }}</td><td class="text-end">{{ r(p.achieved) }}</td>
          <td class="text-end font-weight-bold" :style="{ color: p.realisation === null ? undefined : p.realisation >= 98 ? '#2E7D32' : p.realisation >= 90 ? '#B26A00' : '#C62828' }">{{ p.realisation === null ? '—' : p.realisation + '%' }}</td>
          <td class="text-end">{{ n(p.cases) }}</td></tr></tbody>
      </VTable>
      <p v-else class="tr-empty">Load a price list (product, price per case, valid from) to see where discounts are eating into price.</p>
    </VCard>
  </VContainer>
</template>

<style scoped>
.tr-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
.tr-eyebrow { margin: 0 0 4px; font-size: 13px; font-weight: 600; color: #6A0DAD; }
.tr-title { margin: 0; font-size: 28px; font-weight: 700; letter-spacing: -.6px; color: #16161F; }
.tr-sub { margin: 4px 0 0; font-size: 13px; color: #8A8A99; }
.tr-card-head { padding: 18px 20px 8px; } .tr-card-head h3 { margin: 0; font-size: 16px; font-weight: 700; color: #16161F; } .tr-card-head span { font-size: 12.5px; color: #77778A; }
.tr-empty { padding: 4px 20px 20px; color: #77778A; font-size: 13px; margin: 0; }
</style>
