<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'
import FilterBar from '@/Components/Sales/FilterBar.vue'

const props = defineProps<{
  rows: { data: any[]; total: number; current_page: number; per_page: number }
  totals: any
  coverage: { month: string; rows: number; net_value: number; quantity: number }[]
  filters: Record<string, any>
  options: Record<string, any[]>
  pendingAdjustments: number
  canAdjust: boolean
  canExport: boolean
}>()

const money = (n: any) => 'R ' + Number(n ?? 0).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const num = (n: any) => Number(n ?? 0).toLocaleString('en-ZA', { maximumFractionDigits: 2 })
const exportUrl = computed(() => '/sales/records/export?' + new URLSearchParams(Object.entries(props.filters).map(([k, v]) => [k, String(v)])).toString())
const headers = [
  { title: 'Date', key: 'transaction_date' }, { title: 'Customer', key: 'customer' }, { title: 'Product', key: 'product' },
  { title: 'Cases', key: 'quantity', align: 'end' as const }, { title: 'Gross', key: 'gross_value', align: 'end' as const },
  { title: 'Net', key: 'net_value', align: 'end' as const }, { title: 'Source ref', key: 'external_id' },
]
</script>

<template>
  <VContainer fluid class="pa-4 pa-sm-6">
    <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
      <div>
        <h3 class="text-h5 font-weight-bold mb-1">Sales records</h3>
        <p class="text-body-2 text-medium-emphasis mb-0">Every figure traces back to its import and source row.</p>
      </div>
      <div class="d-flex gap-2">
        <VChip v-if="pendingAdjustments" color="warning" variant="tonal" prepend-icon="tabler-clock">{{ pendingAdjustments }} correction(s) awaiting approval</VChip>
        <VBtn v-if="canExport" variant="tonal" prepend-icon="tabler-download" :href="exportUrl">Export CSV</VBtn>
      </div>
    </div>

    <FilterBar url="/sales/records" :filters="filters" :options="options" />

    <VRow class="mb-2">
      <VCol v-for="k in [['Net sales', money(totals.net_value)], ['Gross sales', money(totals.gross_value)], ['Cases', num(totals.quantity)], ['Customers', num(totals.customers)], ['Products', num(totals.products)], ['Records', num(totals.rows)]]" :key="k[0]" cols="6" md="2">
        <VCard elevation="0" border rounded="lg"><VCardText class="py-3"><div class="text-caption text-medium-emphasis">{{ k[0] }}</div><div class="text-h6">{{ k[1] }}</div></VCardText></VCard>
      </VCol>
    </VRow>

    <VRow>
      <VCol cols="12" lg="9">
        <VCard elevation="0" border rounded="lg">
          <VDataTableServer :headers="headers" :items="rows.data" :items-length="rows.total" :items-per-page="rows.per_page" :page="rows.current_page" density="compact" hover
                            @update:options="o => (o.page !== rows.current_page || o.itemsPerPage !== rows.per_page) && router.get('/sales/records', { ...filters, page: o.page, per_page: o.itemsPerPage }, { preserveState: true })"
                            @click:row="(_: any, { item }: any) => router.get(`/sales/records/${item.id}`)">
            <template #item.customer="{ item }">{{ item.customer_name ?? '—' }} <span class="text-caption text-medium-emphasis">{{ item.customer_code }}</span></template>
            <template #item.product="{ item }">{{ item.product_name ?? '—' }} <span class="text-caption text-medium-emphasis">{{ item.product_code }}</span></template>
            <template #item.quantity="{ item }">{{ num(item.quantity) }} {{ item.unit_of_measure ?? '' }}</template>
            <template #item.gross_value="{ item }">{{ money(item.gross_value) }}</template>
            <template #item.net_value="{ item }">{{ money(item.net_value) }} <VIcon v-if="item.is_adjusted" icon="tabler-pencil" size="14" color="warning" title="Corrected" /></template>
          </VDataTableServer>
        </VCard>
      </VCol>
      <VCol cols="12" lg="3">
        <VCard elevation="0" border rounded="lg" title="Data coverage" subtitle="Net sales loaded per month">
          <VTable density="compact">
            <tbody>
              <tr v-for="c in coverage" :key="c.month"><td>{{ c.month }}</td><td class="text-end">{{ money(c.net_value) }}</td><td class="text-end text-caption">{{ num(c.rows) }}</td></tr>
              <tr v-if="!coverage.length"><td class="text-medium-emphasis">No sales loaded yet. Use the Import Centre.</td></tr>
            </tbody>
          </VTable>
        </VCard>
      </VCol>
    </VRow>
  </VContainer>
</template>
