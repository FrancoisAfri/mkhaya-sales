<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { GridItem, GridLayout } from 'grid-layout-plus'
import { computed, reactive, ref } from 'vue'
import RemoteSelect from '@/Components/Sales/RemoteSelect.vue'
import Visual from '@/Components/BI/Visual.vue'
import { GROUPS, VISUALS, visual } from '@/Components/BI/visuals'

const props = defineProps<{
  dashboard: any; landing: boolean; editing: boolean; canEdit: boolean; filters: Record<string, any>
  options: Record<string, any[]>; catalogue: { measures: Record<string, { label: string; format: string }>; dimensions: Record<string, string>; periods: string[]; grains: string[] }
  bookmarks: { id: number; name: string; state: any }[]; roles: string[]; lastSale: string | null
}>()
const page = usePage<any>()
const userName = computed(() => (page.props.auth?.user?.name ?? '').split(' ')[0] || 'there')
const greeting = computed(() => { const h = new Date().getHours(); return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening' })

// ── Filters (global; widgets may override their own period) ──
const f = reactive<Record<string, any>>({ ...props.filters })
const crossLabels = reactive<Record<string, string>>({})
const appliedFilters = computed(() => Object.fromEntries(Object.entries(f).filter(([, v]) => v !== null && v !== undefined && v !== '')))
function setFilter(k: string, v: any, label?: string) { f[k] = v; if (label) crossLabels[k] = label }
function clearFilter(k: string) { delete f[k]; delete crossLabels[k] }
function onCrossFilter(e: { dimension: string; key: any; label: string }) { setFilter(`${e.dimension}_id`, e.key, e.label) }
const lookup = (k: string) => ({ customer_id: 'customers', product_id: 'products', category_id: 'categories', brand_id: 'brands', region_id: 'regions', customer_group_id: 'customer_groups', sales_team_id: 'sales_teams', salesperson_id: 'salespeople', business_unit_id: 'business_units' } as any)[k]
const chipLabel = (k: string, v: any) => crossLabels[k] ?? props.options[lookup(k)]?.find((o: any) => o.value === Number(v))?.title ?? v
const chips = computed(() => Object.entries(appliedFilters.value).filter(([k]) => !['from', 'to'].includes(k)))

// ── Layout ──
const edit = ref(props.editing)
const widgets = ref<any[]>(JSON.parse(JSON.stringify(props.dashboard.widgets ?? [])).map((w: any) => ({ ...w, i: w.id })))
const dirty = ref(false)
const saving = ref(false)
async function save() {
  saving.value = true
  try {
    await axios.put(`/sales/bi/${props.dashboard.uuid}`, { name: name.value, widgets: widgets.value.map(({ i, ...w }) => ({ ...w, id: String(i) })) }, { headers: { Accept: 'application/json' } })
    dirty.value = false; edit.value = false
  } finally { saving.value = false }
}
const name = ref(props.dashboard.name)

// ── Library & configuration ──
const library = ref(false)
const libSearch = ref('')
const libGroup = ref<string | null>(null)
const libItems = computed(() => VISUALS.filter(v => (!libGroup.value || v.group === libGroup.value) && (!libSearch.value || v.name.toLowerCase().includes(libSearch.value.toLowerCase()))))
function addVisual(key: string) {
  const vis = visual(key)
  const id = 'w' + Date.now().toString(36)
  const kpi = vis.kind === 'kpi'
  widgets.value.push({ i: id, id, type: key, title: vis.name, config: { kind: vis.kind, period: kpi ? 'mtd' : 'ytd', ...JSON.parse(JSON.stringify(vis.defaults ?? {})) }, x: 0, y: 999, w: kpi ? 3 : 6, h: kpi ? 3 : 8 })
  library.value = false; dirty.value = true
}
const configuring = ref<any>(null)
const cfgVisual = computed(() => configuring.value ? visual(configuring.value.type) : null)
const measureItems = computed(() => Object.entries(props.catalogue.measures).map(([value, m]) => ({ value, title: m.label })))
const dimItems = computed(() => Object.entries(props.catalogue.dimensions).map(([value, title]) => ({ value, title })))
const periodItems = [{ value: 'dashboard', title: 'Dashboard date filter' }, { value: 'mtd', title: 'Month to date' }, { value: 'last_month', title: 'Last month' }, { value: 'qtd', title: 'Quarter to date' }, { value: 'ytd', title: 'Year to date' }, { value: 'last_12_months', title: 'Last 12 months' }, { value: 'last_24_months', title: 'Last 24 months' }, { value: 'all', title: 'All data' }]
function duplicate(w: any) { const id = 'w' + Date.now().toString(36); widgets.value.push({ ...JSON.parse(JSON.stringify(w)), i: id, id, y: w.y + w.h }); dirty.value = true }
function remove(w: any) { widgets.value = widgets.value.filter(x => x.i !== w.i); dirty.value = true }

// ── Share & bookmarks ──
const share = ref(false)
const shareForm = reactive({ visibility: props.dashboard.visibility, shared_roles: props.dashboard.shared_roles ?? [] })
async function saveShare() { await axios.put(`/sales/bi/${props.dashboard.uuid}`, shareForm, { headers: { Accept: 'application/json' } }); share.value = false }
const bmName = ref('')
function saveBookmark() { router.post(`/sales/bi/${props.dashboard.uuid}/bookmarks`, { name: bmName.value, state: appliedFilters.value }, { preserveScroll: true, onSuccess: () => { bmName.value = '' } }) }
function applyBookmark(b: any) { Object.keys(f).forEach(k => delete f[k]); Object.assign(f, b.state) }

const presets: [string, () => [string, string]][] = [
  ['MTD', () => { const d = anchor(); return [iso(new Date(d.getFullYear(), d.getMonth(), 1)), iso(d)] }],
  ['QTD', () => { const d = anchor(); return [iso(new Date(d.getFullYear(), Math.floor(d.getMonth() / 3) * 3, 1)), iso(d)] }],
  ['YTD', () => { const d = anchor(); return [iso(new Date(d.getFullYear(), 0, 1)), iso(d)] }],
  ['12M', () => { const d = anchor(); return [iso(new Date(d.getFullYear() - 1, d.getMonth(), d.getDate() + 1)), iso(d)] }],
]
function anchor() { return props.lastSale ? new Date(props.lastSale) : new Date() }
function iso(d: Date) { return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10) }
function exportPdf() { window.print() }
</script>

<template>
  <div class="bi-page">
    <header class="bi-header">
      <div>
        <p v-if="landing" class="bi-eyebrow">{{ greeting }}, {{ userName }}</p>
        <VTextField v-if="edit" v-model="name" variant="plain" density="compact" hide-details class="bi-title-edit" @update:model-value="dirty = true" />
        <h1 v-else class="bi-title">{{ name }}</h1>
        <p class="bi-sub">Data up to {{ lastSale ?? '—' }} · figures in rand, excluding VAT</p>
      </div>
      <div class="bi-header__actions no-print">
        <template v-if="edit">
          <VBtn variant="tonal" prepend-icon="tabler-layout-grid-add" @click="library = true">Add visual</VBtn>
          <VBtn variant="text" @click="router.reload()">Cancel</VBtn>
          <VBtn color="primary" :loading="saving" prepend-icon="tabler-device-floppy" @click="save">Save</VBtn>
        </template>
        <template v-else>
          <VMenu v-if="bookmarks.length || true" location="bottom end">
            <template #activator="{ props: p }"><VBtn v-bind="p" variant="text" icon="tabler-bookmark" title="Saved views" /></template>
            <VCard min-width="260" class="pa-2">
              <VList density="compact"><VListItem v-for="b in bookmarks" :key="b.id" :title="b.name" prepend-icon="tabler-bookmark" @click="applyBookmark(b)" /></VList>
              <div class="d-flex gap-2 pa-2"><VTextField v-model="bmName" placeholder="Save current view as…" density="compact" variant="outlined" hide-details /><VBtn icon="tabler-check" size="small" color="primary" :disabled="!bmName" @click="saveBookmark" /></div>
            </VCard>
          </VMenu>
          <VBtn variant="text" icon="tabler-printer" title="Export to PDF / print" @click="exportPdf" />
          <VBtn v-if="canEdit" variant="text" icon="tabler-share" title="Share" @click="share = true" />
          <VBtn variant="tonal" prepend-icon="tabler-layout-dashboard" href="/sales/bi">All dashboards</VBtn>
          <VBtn v-if="canEdit" color="primary" prepend-icon="tabler-edit" @click="edit = true">Edit</VBtn>
        </template>
      </div>
    </header>

    <div class="bi-filters no-print">
      <div class="bi-seg">
        <button v-for="[l, fn] in presets" :key="l" :class="{ on: false }" @click="[f.from, f.to] = fn()">{{ l }}</button>
      </div>
      <input v-model="f.from" type="date" class="bi-date"> <span class="bi-muted">→</span> <input v-model="f.to" type="date" class="bi-date">
      <RemoteSelect :model-value="f.customer_id" resource="customers" :initial="options.customers" label="Customer" class="bi-filter" @update:model-value="v => setFilter('customer_id', v)" />
      <RemoteSelect :model-value="f.product_id" resource="products" :initial="options.products" label="Product" class="bi-filter" @update:model-value="v => setFilter('product_id', v)" />
      <VAutocomplete v-for="[k, o, l] in [['category_id', 'categories', 'Category'], ['brand_id', 'brands', 'Brand'], ['region_id', 'regions', 'Region'], ['customer_group_id', 'customer_groups', 'Group']]" :key="k"
                     :model-value="f[k] ? Number(f[k]) : null" :items="options[o] ?? []" :label="l" clearable density="compact" variant="outlined" hide-details class="bi-filter"
                     @update:model-value="v => v ? setFilter(k, v) : clearFilter(k)" />
    </div>
    <div v-if="chips.length" class="bi-chips no-print">
      <span class="bi-muted me-1">Filtered by</span>
      <VChip v-for="[k, v] in chips" :key="k" closable size="small" color="primary" variant="tonal" @click:close="clearFilter(k)">{{ chipLabel(k, v) }}</VChip>
      <VBtn size="x-small" variant="text" @click="chips.forEach(([k]) => clearFilter(k))">Clear all</VBtn>
    </div>

    <GridLayout v-model:layout="widgets" :col-num="12" :row-height="30" :margin="[16, 16]" :is-draggable="edit" :is-resizable="edit" :responsive="true" :breakpoints="{ lg: 1200, md: 996, sm: 768, xs: 480, xxs: 0 }" :cols="{ lg: 12, md: 12, sm: 6, xs: 2, xxs: 1 }" use-css-transforms @layout-updated="dirty = edit">
      <GridItem v-for="w in widgets" :key="w.i" :i="w.i" :x="w.x" :y="w.y" :w="w.w" :h="w.h" :min-w="2" :min-h="3" drag-ignore-from=".bi-card__body, .v-btn">
        <Visual :widget="w" :filters="appliedFilters" :editing="edit" @filter="onCrossFilter" @edit="configuring = w" @remove="remove(w)" @duplicate="duplicate(w)" />
      </GridItem>
    </GridLayout>
    <div v-if="!widgets.length" class="bi-blank"><VIcon icon="tabler-layout-dashboard" size="40" /><p>This dashboard is empty.</p><VBtn color="primary" @click="edit = true; library = true">Add your first visual</VBtn></div>

    <!-- Visual library -->
    <VDialog v-model="library" max-width="1000" scrollable>
      <VCard class="bi-lib">
        <div class="bi-lib__head"><h3>Visual library</h3><VTextField v-model="libSearch" placeholder="Search visuals" prepend-inner-icon="tabler-search" density="compact" variant="outlined" hide-details style="max-width: 280px" /></div>
        <div class="bi-lib__body">
          <nav><button :class="{ on: !libGroup }" @click="libGroup = null">All</button><button v-for="g in GROUPS" :key="g" :class="{ on: libGroup === g }" @click="libGroup = g">{{ g }}</button></nav>
          <div class="bi-lib__grid">
            <button v-for="vis in libItems" :key="vis.key" class="bi-lib__item" @click="addVisual(vis.key)"><VIcon :icon="vis.icon" size="22" /><span>{{ vis.name }}</span><small>{{ vis.group }}</small></button>
          </div>
        </div>
      </VCard>
    </VDialog>

    <!-- Visual configuration -->
    <VNavigationDrawer :model-value="!!configuring" location="right" temporary width="380" @update:model-value="v => !v && (configuring = null)">
      <div v-if="configuring" class="pa-5 d-flex flex-column gap-4">
        <div class="d-flex align-center justify-space-between"><h3 class="text-h6">Visual settings</h3><VBtn icon="tabler-x" variant="text" size="small" @click="configuring = null" /></div>
        <VTextField v-model="configuring.title" label="Title" density="compact" variant="outlined" hide-details @update:model-value="dirty = true" />
        <VSelect v-model="configuring.type" :items="VISUALS.filter(x => x.kind === cfgVisual?.kind).map(x => ({ value: x.key, title: x.name }))" label="Visual type" density="compact" variant="outlined" hide-details @update:model-value="dirty = true" />
        <template v-if="cfgVisual?.special === 'text'"><VTextarea v-model="configuring.config.text" label="Text" rows="5" density="compact" variant="outlined" hide-details /></template>
        <template v-else-if="!['none'].includes(cfgVisual?.kind ?? '')">
          <div class="bi-cfg-label">Data</div>
          <VSelect v-model="configuring.config.measures" :items="measureItems" label="Measures" multiple chips closable-chips density="compact" variant="outlined" hide-details @update:model-value="dirty = true" />
          <VSelect v-if="!['kpi', 'time', 'forecast', 'narrative', 'cohort', 'calendar'].includes(cfgVisual!.kind)" v-model="configuring.config.dimension" :items="dimItems" label="Category / dimension" density="compact" variant="outlined" hide-details @update:model-value="dirty = true" />
          <VSelect v-if="['time', 'matrix', 'sankey', 'treemap', 'boxplot'].includes(cfgVisual!.kind)" v-model="configuring.config.series" :items="[{ value: null, title: 'None' }, ...dimItems]" label="Series / second level" density="compact" variant="outlined" hide-details @update:model-value="dirty = true" />
          <VSelect v-if="cfgVisual!.kind === 'time'" v-model="configuring.config.grain" :items="catalogue.grains" label="Time interval" density="compact" variant="outlined" hide-details @update:model-value="dirty = true" />
          <VSelect v-model="configuring.config.period" :items="periodItems" label="Period" density="compact" variant="outlined" hide-details @update:model-value="dirty = true" />
          <div class="bi-cfg-label">Display</div>
          <div class="d-flex gap-3">
            <VTextField v-model.number="configuring.config.top" type="number" label="Top N" density="compact" variant="outlined" hide-details />
            <VSelect v-model="configuring.config.sort" :items="[{ value: 'desc', title: 'Largest first' }, { value: 'asc', title: 'Smallest first' }, { value: 'chrono', title: 'A–Z / time' }]" label="Sort" density="compact" variant="outlined" hide-details />
          </div>
          <VSwitch v-model="configuring.config.other" label="Group the rest as “Other”" density="compact" hide-details inset />
        </template>
        <p class="text-caption text-medium-emphasis">All figures are calculated on the server by the shared measure engine, so this visual always agrees with reports and targets.</p>
      </div>
    </VNavigationDrawer>

    <VDialog v-model="share" max-width="480">
      <VCard title="Share dashboard">
        <VCardText class="d-flex flex-column gap-3">
          <VRadioGroup v-model="shareForm.visibility" hide-details><VRadio value="private" label="Only me" /><VRadio value="shared" label="Selected roles" /><VRadio value="organisation" label="Everyone in the organisation" /></VRadioGroup>
          <VSelect v-if="shareForm.visibility === 'shared'" v-model="shareForm.shared_roles" :items="roles" label="Roles" multiple chips density="compact" variant="outlined" />
        </VCardText>
        <VCardActions><VSpacer /><VBtn variant="text" @click="share = false">Cancel</VBtn><VBtn color="primary" @click="saveShare">Save</VBtn></VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.bi-page { padding: 28px 28px 48px; background: #F7F7FA; min-height: calc(100vh - 140px); }
.bi-header { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin: 0 8px 20px; }
.bi-eyebrow { margin: 0 0 4px; font-size: 13px; color: #6A0DAD; font-weight: 600; }
.bi-title { margin: 0; font-size: 28px; font-weight: 700; letter-spacing: -.6px; color: #16161F; }
.bi-title-edit :deep(input) { font-size: 26px; font-weight: 700; }
.bi-sub { margin: 4px 0 0; font-size: 13px; color: #8A8A99; }
.bi-header__actions { display: flex; gap: 8px; align-items: center; }
.bi-filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; background: #fff; border: 1px solid #EEEEF3; border-radius: 16px; padding: 10px 12px; margin: 0 8px 12px; }
.bi-filter { min-width: 150px; max-width: 210px; }
.bi-seg { display: inline-flex; background: #F3F1F8; border-radius: 10px; padding: 3px; }
.bi-seg button { border: 0; background: transparent; padding: 5px 12px; font-size: 12px; font-weight: 600; color: #5B5B6E; border-radius: 8px; cursor: pointer; }
.bi-seg button:hover { background: #fff; color: #6A0DAD; }
.bi-date { border: 1px solid #E4E4EC; border-radius: 10px; padding: 7px 10px; font-size: 13px; color: #2B2B38; }
.bi-chips { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; margin: 0 8px 8px; font-size: 12px; }
.bi-muted { color: #8A8A99; font-size: 12px; }
.bi-blank { text-align: center; padding: 80px 0; color: #8A8A99; }
.bi-lib { border-radius: 20px !important; overflow: hidden; }
.bi-lib__head { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid #EEEEF3; }
.bi-lib__head h3 { margin: 0; font-size: 18px; }
.bi-lib__body { display: grid; grid-template-columns: 190px 1fr; min-height: 480px; }
.bi-lib__body nav { border-right: 1px solid #EEEEF3; padding: 12px; display: flex; flex-direction: column; gap: 2px; }
.bi-lib__body nav button { text-align: left; border: 0; background: transparent; padding: 8px 12px; border-radius: 10px; font-size: 13px; color: #45455A; cursor: pointer; }
.bi-lib__body nav button.on, .bi-lib__body nav button:hover { background: #F3EEFA; color: #6A0DAD; font-weight: 600; }
.bi-lib__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; padding: 16px; align-content: start; overflow: auto; max-height: 64vh; }
.bi-lib__item { display: flex; flex-direction: column; align-items: flex-start; gap: 6px; padding: 14px; border: 1px solid #EEEEF3; border-radius: 14px; background: #fff; cursor: pointer; text-align: left; transition: all .15s; color: #6A0DAD; }
.bi-lib__item:hover { border-color: #C9B3E6; box-shadow: 0 8px 22px rgba(106,13,173,.08); transform: translateY(-1px); }
.bi-lib__item span { font-size: 13px; font-weight: 600; color: #22222E; } .bi-lib__item small { font-size: 11px; color: #8A8A99; }
.bi-cfg-label { font-size: 11px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; color: #8A8A99; margin-top: 6px; }
:deep(.vgl-item--placeholder) { background: #E9DDF7 !important; border-radius: 18px; opacity: .6; }
@media print { .no-print { display: none !important; } .bi-page { background: #fff; padding: 0; } }
</style>
