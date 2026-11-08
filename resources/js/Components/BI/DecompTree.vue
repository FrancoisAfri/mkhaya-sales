<script setup lang="ts">
// Interactive decomposition tree (BI spec §F): start from the total, pick any dimension to split
// by, click a bar to open the next level, and choose that level's dimension. Each level is one
// server query with the path so far as filters.
import axios from 'axios'
import { computed, reactive, ref, watch } from 'vue'
import { fmt } from './visuals'

const props = defineProps<{ widget: any; filters: Record<string, any> }>()
const DIMS: Record<string, string> = { region: 'Region', province: 'Province', customer_group: 'Customer group', category: 'Category', brand: 'Brand', sales_team: 'Sales team', salesperson: 'Salesperson', distribution_centre: 'Distribution centre', customer: 'Customer', product: 'Product' }
const TEXT = ['province', 'city']
const filterKey = (d: string) => (TEXT.includes(d) ? d : `${d}_id`)
const measure = computed(() => props.widget.config?.measures?.[0] ?? 'net_value')
const period = computed(() => props.widget.config?.period ?? 'ytd')

type Level = { dim: string; rows: { key: any; label: string; value: number }[]; total: number; selected: any; loading: boolean }
const total = ref<number | null>(null)
const levels = reactive<Level[]>([])

const pathFilters = (upTo: number) => Object.fromEntries(levels.slice(0, upTo).map(l => [filterKey(l.dim), l.selected]))
const used = (i: number) => levels.slice(0, i).map(l => l.dim)

async function query(dim: string | null, extra: Record<string, any>) {
  const config = { kind: dim ? 'category' : 'kpi', measures: [measure.value], dimension: dim ?? 'customer', top: 8, other: true, period: period.value }
  return (await axios.post('/sales/bi/query', { config, filters: { ...props.filters, ...extra } })).data
}
async function loadTotal() {
  levels.splice(0)
  total.value = (await query(null, {})).values?.[measure.value] ?? 0
  await openLevel(0, props.widget.config?.dimension ?? 'category')
}
async function openLevel(i: number, dim: string) {
  levels.splice(i)
  const lvl: Level = reactive({ dim, rows: [], total: 0, selected: null, loading: true })
  levels.push(lvl)
  const d = await query(dim, pathFilters(i))
  lvl.rows = (d.categories ?? []).map((label: string, j: number) => ({ key: d.keys[j], label, value: d.series[0].data[j] ?? 0 }))
  lvl.total = d.total ?? lvl.rows.reduce((a, r) => a + r.value, 0)
  lvl.loading = false
}
function select(i: number, key: any) {
  if (key === 'other') return
  levels[i].selected = levels[i].selected === key ? null : key
  levels.splice(i + 1)
  if (levels[i].selected !== null) {
    const next = Object.keys(DIMS).find(d => !used(i + 1).includes(d) && d !== 'customer' && d !== 'product') ?? 'customer'
    openLevel(i + 1, next)
  }
}
const pct = (v: number, t: number) => (t ? Math.max(2, v / t * 100) : 0)
watch(() => [props.filters, props.widget.config], loadTotal, { deep: true, immediate: true })
</script>

<template>
  <div class="dt">
    <div class="dt__col dt__root">
      <small>{{ measure === 'net_value' ? 'Net sales' : measure }}</small>
      <div class="dt__node on"><strong>{{ total === null ? '…' : fmt(total) }}</strong></div>
    </div>
    <div v-for="(l, i) in levels" :key="i" class="dt__col">
      <VSelect :model-value="l.dim" :items="Object.entries(DIMS).filter(([d]) => !used(i).includes(d)).map(([value, title]) => ({ value, title }))" density="compact" variant="plain" hide-details class="dt__dim" @update:model-value="d => openLevel(i, d)" />
      <div v-if="l.loading" class="dt__loading"><VProgressCircular indeterminate size="18" width="2" color="primary" /></div>
      <button v-for="r in l.rows" v-else :key="r.key" class="dt__node" :class="{ on: l.selected === r.key, dim: l.selected !== null && l.selected !== r.key, other: r.key === 'other' }" :title="r.label" @click="select(i, r.key)">
        <span class="dt__label">{{ r.label }}</span>
        <span class="dt__bar"><i :style="{ width: pct(r.value, l.total) + '%' }" /></span>
        <span class="dt__val">{{ fmt(r.value) }}</span>
      </button>
    </div>
    <div v-if="levels.length && levels[levels.length - 1].selected === null && !levels[levels.length - 1].loading" class="dt__hint">Click a bar to split it further</div>
  </div>
</template>

<style scoped>
.dt { display: flex; gap: 18px; height: 100%; overflow-x: auto; overflow-y: hidden; padding-bottom: 4px; }
.dt__col { flex: 0 0 210px; display: flex; flex-direction: column; gap: 4px; min-height: 0; overflow-y: auto; }
.dt__root { flex-basis: 130px; justify-content: center; }
.dt__root small { font-size: 11px; color: #8A8A99; font-weight: 600; }
.dt__dim { font-size: 12px; font-weight: 700; color: #6A0DAD; }
.dt__dim :deep(.v-field__input) { font-size: 12px; font-weight: 700; color: #6A0DAD; min-height: 28px; padding-top: 0; }
.dt__node { display: grid; grid-template-columns: 1fr; gap: 3px; padding: 7px 9px; border: 1px solid #EEEEF3; border-radius: 10px; background: #fff; text-align: left; cursor: pointer; transition: all .15s; }
.dt__node:hover { border-color: #D9C8EE; }
.dt__node.on { border-color: #6A0DAD; background: #F8F4FD; box-shadow: 0 0 0 3px rgba(106,13,173,.08); }
.dt__node.dim { opacity: .5; }
.dt__node.other { cursor: default; background: #FBFBFD; }
.dt__node strong { font-family: Montserrat, sans-serif; font-size: 18px; color: #16161F; }
.dt__label { font-size: 12px; font-weight: 600; color: #2B2B38; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dt__bar { height: 6px; background: #F3F1F8; border-radius: 99px; overflow: hidden; }
.dt__bar i { display: block; height: 100%; background: #6A0DAD; border-radius: 99px; }
.dt__val { font-size: 11px; color: #77778A; }
.dt__loading { padding: 20px; display: flex; justify-content: center; }
.dt__hint { align-self: center; font-size: 12px; color: #A0A0B0; white-space: nowrap; }
</style>
