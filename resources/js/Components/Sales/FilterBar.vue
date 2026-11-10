<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { computed, reactive, ref } from 'vue'
import { useDisplay } from 'vuetify'
import RemoteSelect from './RemoteSelect.vue'

/** The global sales filters (SRS §13.6), shared by records, dashboards and reports. */
const props = defineProps<{ url: string; filters: Record<string, any>; options: Record<string, { value: number; title: string }[]>; extra?: Record<string, any> }>()
const f = reactive<Record<string, any>>({ ...props.filters })
const selects: [string, string, string][] = [
  ['customer_id', 'customers', 'Customer'], ['customer_group_id', 'customer_groups', 'Customer group'],
  ['product_id', 'products', 'Product'], ['category_id', 'categories', 'Category'], ['brand_id', 'brands', 'Brand'],
  ['region_id', 'regions', 'Region'], ['sales_team_id', 'sales_teams', 'Sales team'], ['salesperson_id', 'salespeople', 'Salesperson'],
  ['business_unit_id', 'business_units', 'Business unit'],
]
const presets: [string, () => [string, string]][] = [
  ['MTD', () => { const d = new Date(); return [iso(new Date(d.getFullYear(), d.getMonth(), 1)), iso(d)] }],
  ['Last month', () => { const d = new Date(); return [iso(new Date(d.getFullYear(), d.getMonth() - 1, 1)), iso(new Date(d.getFullYear(), d.getMonth(), 0))] }],
  ['YTD', () => { const d = new Date(); return [iso(new Date(d.getFullYear(), 0, 1)), iso(d)] }],
  ['Last 12 months', () => { const d = new Date(); return [iso(new Date(d.getFullYear() - 1, d.getMonth(), d.getDate() + 1)), iso(d)] }],
]
function iso(d: Date) { return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10) }
function apply() {
  const q: Record<string, any> = { ...(props.extra ?? {}) }
  Object.entries(f).forEach(([k, v]) => { if (v !== null && v !== undefined && v !== '') q[k] = v })
  router.get(props.url, q, { preserveState: true, preserveScroll: true })
}
function preset(fn: () => [string, string]) { [f.from, f.to] = fn(); apply() }
// Phones (gap 1): filters fold behind one button so the numbers are on the first screen.
const { smAndDown } = useDisplay()
const open = ref(false)
const active = computed(() => Object.entries(f).filter(([, v]) => v !== null && v !== undefined && v !== '').length)
function reset() { Object.keys(f).forEach(k => delete f[k]); apply() }
</script>

<template>
  <VBtn v-if="smAndDown" block variant="tonal" color="primary" class="mb-3" :prepend-icon="open ? 'tabler-chevron-up' : 'tabler-adjustments-horizontal'" @click="open = !open">
    {{ open ? 'Hide filters' : 'Filters' }}<VChip v-if="active" size="x-small" color="primary" class="ms-2">{{ active }}</VChip>
  </VBtn>
  <VCard v-show="!smAndDown || open" elevation="0" border rounded="lg" class="mb-4">
    <VCardText class="d-flex flex-wrap gap-2 align-center">
      <VTextField v-model="f.from" type="date" label="From" density="compact" variant="outlined" hide-details style="max-width: 160px" @change="apply" />
      <VTextField v-model="f.to" type="date" label="To" density="compact" variant="outlined" hide-details style="max-width: 160px" @change="apply" />
      <VChip v-for="[label, fn] in presets" :key="label" size="small" variant="tonal" @click="preset(fn)">{{ label }}</VChip>
      <RemoteSelect v-model="f.customer_id" resource="customers" :initial="options.customers" label="Customer" style="min-width: 200px; max-width: 260px" @update:model-value="apply" />
      <RemoteSelect v-model="f.product_id" resource="products" :initial="options.products" label="Product" style="min-width: 200px; max-width: 260px" @update:model-value="apply" />
      <template v-for="[key, opt, label] in selects.filter(([k]) => !['customer_id', 'product_id'].includes(k))" :key="key">
        <VAutocomplete v-if="(options[opt] ?? []).length" v-model="f[key]" :items="options[opt]" :label="label" clearable density="compact" variant="outlined" hide-details
                       style="min-width: 170px; max-width: 220px" @update:model-value="apply" />
      </template>
      <VBtn variant="text" size="small" @click="reset">Reset</VBtn>
    </VCardText>
  </VCard>
</template>
