<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { GridItem, GridLayout } from 'grid-layout-plus'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import RemoteSelect from '@/Components/Sales/RemoteSelect.vue'
import Visual from '@/Components/BI/Visual.vue'
import { GROUPS, VISUALS, visual } from '@/Components/BI/visuals'

const props = defineProps<{
  dashboard: any; landing: boolean; editing: boolean; canEdit: boolean; filters: Record<string, any>
  options: Record<string, any[]>; catalogue: { measures: Record<string, { label: string; format: string }>; dimensions: Record<string, string>; periods: string[]; grains: string[] }
  bookmarks: { id: number; name: string; state: any }[]; roles: string[]; lastSale: string | null; canCertify: boolean; canShareLink: boolean
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
function onCrossFilter(e: { dimension: string; key: any; label: string }) { setFilter(['province', 'city'].includes(e.dimension) ? e.dimension : `${e.dimension}_id`, e.key, e.label) }
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
// Filters that belong to one visual (gap 4) — stored on the widget, merged over the dashboard filters by the engine.
const cfgFilters = computed<Record<string, any>>(() => configuring.value?.config?.filters ?? {})
function setCfgFilter(k: string, v: any) {
  const c = configuring.value.config
  const next = { ...(c.filters ?? {}) }
  if (v === null || v === undefined || v === '') delete next[k]; else next[k] = v
  c.filters = next
  dirty.value = true
}
function duplicate(w: any) { const id = 'w' + Date.now().toString(36); widgets.value.push({ ...JSON.parse(JSON.stringify(w)), i: id, id, y: w.y + w.h }); dirty.value = true }
function remove(w: any) { widgets.value = widgets.value.filter(x => x.i !== w.i); dirty.value = true }

// ── Share & bookmarks ──
const share = ref(false)
const shareForm = reactive({ visibility: props.dashboard.visibility, shared_roles: props.dashboard.shared_roles ?? [] })
// ── Share links for people outside the system (gap 8) ──
const links = ref<any[]>([])
const linkDays = ref(30)
const copied = ref('')
async function loadLinks() { if (props.canShareLink) links.value = (await axios.get(`/sales/bi/${props.dashboard.uuid}/shares`)).data }
async function makeLink() { await axios.post(`/sales/bi/${props.dashboard.uuid}/shares`, { days: linkDays.value, filters: appliedFilters.value }); await loadLinks() }
async function revokeLink(id: number) { await axios.delete(`/sales/bi/${props.dashboard.uuid}/shares/${id}`); await loadLinks() }
function copyLink(url: string) { navigator.clipboard?.writeText(url); copied.value = url; setTimeout(() => (copied.value = ''), 2000) }
const shareText = (url: string) => encodeURIComponent(`${props.dashboard.name} — sales dashboard: ${url}`)
watch(share, v => v && loadLinks())

// ── Comments with @mentions (gap 9) ──
const commentsOpen = ref(false)
const comments = ref<any[]>([])
const commentWidget = ref<string | null>(null)
const newComment = ref('')
const people = ref<any[]>([])
const sending = ref(false)
const commentNote = ref('')
async function loadComments() { comments.value = (await axios.get(`/sales/bi/${props.dashboard.uuid}/comments`)).data }
const openComments = computed(() => comments.value.filter(c => !c.resolved))
const shownComments = computed(() => comments.value.filter(c => !commentWidget.value || c.widget_id === commentWidget.value))
const widgetTitle = (id: string | null) => widgets.value.find(w => w.i === id)?.title ?? 'Whole dashboard'
function openCommentsFor(id: string | null) { commentWidget.value = id; commentsOpen.value = true; if (!people.value.length) axios.get('/sales/bi/people').then(r => (people.value = r.data)) }
async function sendComment() {
  if (!newComment.value.trim()) return
  sending.value = true
  try {
    const r = (await axios.post(`/sales/bi/${props.dashboard.uuid}/comments`, { body: newComment.value, widget_id: commentWidget.value })).data
    commentNote.value = r.notified?.length ? `Notified ${r.notified.join(', ')}` : ''
    newComment.value = ''; await loadComments()
  } finally { sending.value = false }
}
async function resolveComment(c: any) { await axios.post(`/sales/bi/${props.dashboard.uuid}/comments/${c.id}/resolve`, { resolved: !c.resolved }); await loadComments() }
const mentionHint = computed(() => { const m = newComment.value.match(/@([\p{L}]*)$/u); return m ? people.value.filter(p => p.name.toLowerCase().startsWith(m[1].toLowerCase())).slice(0, 5) : [] })
function pickMention(p: any) { newComment.value = newComment.value.replace(/@([\p{L}]*)$/u, p.name.includes(' ') ? `@"${p.name}" ` : `@${p.name} `) }
onMounted(loadComments)

// ── Board pack (gap 10): PowerPoint or print-ready PDF of this dashboard as it is on screen ──
const visualRefs: Record<string, any> = {}
const packing = ref(false)
async function boardPack(format: 'pptx' | 'pdf') {
  packing.value = true
  try {
    const items = [...widgets.value].sort((a, b) => a.y - b.y || a.x - b.x).map(w => visualRefs[w.i]?.snapshot?.()).filter((x: any) => x && (x.image || x.text))
    const period = appliedFilters.value.from ? `${appliedFilters.value.from} to ${appliedFilters.value.to ?? ''}` : `Data up to ${props.lastSale ?? ''}`
    const r = await axios.post(`/sales/bi/${props.dashboard.uuid}/board-pack`, { format, period, items }, { responseType: 'blob' })
    const a = document.createElement('a'); a.href = URL.createObjectURL(r.data); a.download = `${name.value}.${format}`; a.click(); URL.revokeObjectURL(a.href)
  } finally { packing.value = false }
}

// ── Version history and certification (gap 12) ──
const historyOpen = ref(false)
const versions = ref<any[]>([])
const certified = ref(!!props.dashboard.certified)
async function openHistory() { historyOpen.value = true; versions.value = (await axios.get(`/sales/bi/${props.dashboard.uuid}/versions`)).data }
async function restoreVersion(v: any) { if (!confirm(`Restore version ${v.version}? The current layout is kept in the history.`)) return; await axios.post(`/sales/bi/${props.dashboard.uuid}/versions/${v.id}/restore`); router.reload() }
async function toggleCertified() { certified.value = (await axios.post(`/sales/bi/${props.dashboard.uuid}/certify`, { certified: !certified.value })).data.certified }
const when = (s: string) => new Date(s).toLocaleString('en-ZA', { dateStyle: 'medium', timeStyle: 'short' })

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

// ── Ask a question, get a chart (gap 2) ──
const question = ref('')
const asking = ref(false)
const answer = ref<any>(null)
const askError = ref('')
const suggestions = ['Pizza cases this month vs last year', 'Top 10 customers by sales this year', 'Monthly sales trend', 'Which provinces are declining?', 'Sales by retail group this quarter']
async function ask(q?: string) {
  if (q) question.value = q
  if (question.value.trim().length < 3) return
  asking.value = true; askError.value = ''
  try {
    const r = (await axios.post('/sales/bi/ask', { question: question.value })).data
    answer.value = { ...r, widget: { id: 'ask', i: 'ask', type: r.type, title: r.title, config: r.config } }
  } catch (e: any) { askError.value = e.response?.data?.message ?? 'Could not answer that question.' } finally { asking.value = false }
}
function addAnswer() {
  const id = 'w' + Date.now().toString(36)
  const kpi = answer.value.config.kind === 'kpi'
  widgets.value.push({ i: id, id, type: answer.value.type, title: answer.value.title, config: answer.value.config, x: 0, y: 999, w: kpi ? 3 : 6, h: kpi ? 4 : 8 })
  answer.value = null; edit.value = true; dirty.value = true
}
</script>

<template>
  <div class="bi-page">
    <header class="bi-header">
      <div>
        <p v-if="landing" class="bi-eyebrow">{{ greeting }}, {{ userName }}</p>
        <VTextField v-if="edit" v-model="name" variant="plain" density="compact" hide-details class="bi-title-edit" @update:model-value="dirty = true" />
        <h1 v-else class="bi-title">{{ name }} <VChip v-if="certified" size="small" color="success" variant="tonal" prepend-icon="tabler-rosette-discount-check" title="Checked by an administrator">Certified</VChip></h1>
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
          <VMenu location="bottom end">
            <template #activator="{ props: p }"><VBtn v-bind="p" variant="text" icon="tabler-presentation" title="Board pack" :loading="packing" /></template>
            <VList density="compact">
              <VListItem prepend-icon="tabler-file-type-ppt" title="PowerPoint board pack" @click="boardPack('pptx')" />
              <VListItem prepend-icon="tabler-file-type-pdf" title="PDF board pack (one chart per page)" @click="boardPack('pdf')" />
              <VListItem prepend-icon="tabler-printer" title="Print this page" @click="exportPdf" />
            </VList>
          </VMenu>
          <VBtn variant="text" title="Comments" @click="openCommentsFor(null)"><VBadge :model-value="openComments.length > 0" :content="openComments.length" color="primary" floating><VIcon icon="tabler-message-circle" /></VBadge></VBtn>
          <VBtn variant="text" icon="tabler-history" title="Version history" @click="openHistory" />
          <VBtn v-if="canEdit || canShareLink" variant="text" icon="tabler-share" title="Share" @click="share = true" />
          <VBtn v-if="canCertify" variant="text" :icon="certified ? 'tabler-rosette-discount-check-filled' : 'tabler-rosette-discount-check'" :color="certified ? 'success' : undefined" :title="certified ? 'Certified — click to remove' : 'Mark as certified'" @click="toggleCertified" />
          <VBtn variant="tonal" prepend-icon="tabler-layout-dashboard" href="/sales/bi">All dashboards</VBtn>
          <VBtn v-if="canEdit" color="primary" prepend-icon="tabler-edit" @click="edit = true">Edit</VBtn>
        </template>
      </div>
    </header>

    <form class="bi-ask no-print" @submit.prevent="ask()">
      <VIcon icon="tabler-sparkles" color="primary" size="20" />
      <input v-model="question" placeholder="Ask about your sales — e.g. “Pizza cases in Gauteng this month vs last year”" maxlength="300">
      <VBtn type="submit" color="primary" size="small" :loading="asking" :disabled="question.trim().length < 3">Ask</VBtn>
    </form>
    <div v-if="!answer" class="bi-ask-tips no-print"><button v-for="s in suggestions" :key="s" @click="ask(s)">{{ s }}</button></div>
    <VAlert v-if="askError" type="error" variant="tonal" density="compact" class="mx-2 mb-3" closable @click:close="askError = ''">{{ askError }}</VAlert>
    <VDialog :model-value="!!answer" max-width="960" @update:model-value="v => !v && (answer = null)">
      <VCard v-if="answer" class="pa-2">
        <div class="d-flex align-start justify-space-between gap-3 pa-3 pb-1">
          <div><div class="text-caption text-primary font-weight-bold">You asked</div><h3 class="text-h6 mb-1">“{{ question }}”</h3>
            <p class="text-body-2 text-medium-emphasis mb-1">{{ answer.explanation }}</p>
            <div class="d-flex flex-wrap gap-1"><VChip v-for="f in answer.filters_used" :key="f" size="x-small" color="primary" variant="tonal">{{ f }}</VChip>
              <VChip size="x-small" variant="tonal">{{ answer.source === 'ai' ? 'Understood by the AI assistant — check the settings' : 'Understood from keywords' }}</VChip></div></div>
          <VBtn icon="tabler-x" variant="text" size="small" @click="answer = null" />
        </div>
        <div style="height: 420px" class="pa-2"><Visual :widget="answer.widget" :filters="appliedFilters" @filter="onCrossFilter" /></div>
        <VCardActions><VSpacer /><VBtn variant="text" @click="answer = null">Close</VBtn><VBtn v-if="canEdit" color="primary" prepend-icon="tabler-layout-grid-add" @click="addAnswer">Add to this dashboard</VBtn></VCardActions>
      </VCard>
    </VDialog>

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
        <Visual :ref="el => (visualRefs[w.i] = el)" :widget="w" :filters="appliedFilters" :editing="edit" @filter="onCrossFilter" @edit="configuring = w" @remove="remove(w)" @duplicate="duplicate(w)" @comment="openCommentsFor(w.i)" />
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
          <VSelect v-if="!['kpi', 'time', 'forecast', 'narrative', 'cohort', 'calendar', 'geo', 'influencers'].includes(cfgVisual!.kind)" v-model="configuring.config.dimension" :items="dimItems" label="Category / dimension" density="compact" variant="outlined" hide-details @update:model-value="dirty = true" />
          <VSelect v-if="['time', 'matrix', 'sankey', 'treemap', 'boxplot', 'network'].includes(cfgVisual!.kind)" v-model="configuring.config.series" :items="[{ value: null, title: 'None' }, ...dimItems]" label="Series / second level" density="compact" variant="outlined" hide-details @update:model-value="dirty = true" />
          <VSelect v-if="cfgVisual!.kind === 'time'" v-model="configuring.config.grain" :items="catalogue.grains" label="Time interval" density="compact" variant="outlined" hide-details @update:model-value="dirty = true" />
          <VSelect v-model="configuring.config.period" :items="periodItems" label="Period" density="compact" variant="outlined" hide-details @update:model-value="dirty = true" />
          <div class="bi-cfg-label">Display</div>
          <div class="d-flex gap-3">
            <VTextField v-model.number="configuring.config.top" type="number" label="Top N" density="compact" variant="outlined" hide-details />
            <VSelect v-model="configuring.config.sort" :items="[{ value: 'desc', title: 'Largest first' }, { value: 'asc', title: 'Smallest first' }, { value: 'chrono', title: 'A–Z / time' }]" label="Sort" density="compact" variant="outlined" hide-details />
          </div>
          <VSwitch v-model="configuring.config.other" label="Group the rest as “Other”" density="compact" hide-details inset />
          <div class="bi-cfg-label">Filters for this visual only</div>
          <p class="text-caption text-medium-emphasis mt-n2 mb-0">Applied on top of the dashboard filters — e.g. this chart shows Pizza only.</p>
          <RemoteSelect :model-value="cfgFilters.customer_id ?? null" resource="customers" :initial="options.customers" label="Customer" @update:model-value="v => setCfgFilter('customer_id', v)" />
          <RemoteSelect :model-value="cfgFilters.product_id ?? null" resource="products" :initial="options.products" label="Product" @update:model-value="v => setCfgFilter('product_id', v)" />
          <VAutocomplete v-for="[k, o, l] in [['category_id', 'categories', 'Category'], ['brand_id', 'brands', 'Brand'], ['region_id', 'regions', 'Region'], ['customer_group_id', 'customer_groups', 'Customer group']]" :key="k"
                         :model-value="cfgFilters[k] ? Number(cfgFilters[k]) : null" :items="options[o] ?? []" :label="l" clearable density="compact" variant="outlined" hide-details @update:model-value="v => setCfgFilter(k, v)" />
        </template>
        <p class="text-caption text-medium-emphasis">All figures are calculated on the server by the shared measure engine, so this visual always agrees with reports and targets.</p>
      </div>
    </VNavigationDrawer>

    <VDialog v-model="share" max-width="600">
      <VCard title="Share dashboard">
        <VCardText class="d-flex flex-column gap-3">
          <template v-if="canEdit">
            <div class="bi-cfg-label">Who can open it in Mkhaya Sales</div>
            <VRadioGroup v-model="shareForm.visibility" hide-details><VRadio value="private" label="Only me" /><VRadio value="shared" label="Selected roles" /><VRadio value="organisation" label="Everyone in the organisation" /></VRadioGroup>
            <VSelect v-if="shareForm.visibility === 'shared'" v-model="shareForm.shared_roles" :items="roles" label="Roles" multiple chips density="compact" variant="outlined" />
            <div class="d-flex justify-end"><VBtn color="primary" size="small" @click="saveShare">Save access</VBtn></div>
            <VDivider />
          </template>
          <template v-if="canShareLink">
            <div class="bi-cfg-label">Read-only link for people without a login</div>
            <p class="text-caption text-medium-emphasis mt-n2 mb-0">Shows this dashboard with today's filters, as you see it. Anyone with the link can view until it expires; withdraw it any time.</p>
            <div class="d-flex gap-2 align-center"><VSelect v-model="linkDays" :items="[{ value: 1, title: '1 day' }, { value: 7, title: '7 days' }, { value: 30, title: '30 days' }, { value: 90, title: '90 days' }]" label="Valid for" density="compact" variant="outlined" hide-details style="max-width: 160px" />
              <VBtn color="primary" variant="tonal" prepend-icon="tabler-link" @click="makeLink">Create link</VBtn></div>
            <div v-for="l in links" :key="l.id" class="bi-link">
              <div class="bi-link__url" :title="l.url">{{ l.url }}</div>
              <div class="bi-link__meta">Expires {{ String(l.expires_at).slice(0, 10) }} · {{ l.views }} views</div>
              <div class="d-flex gap-1">
                <VBtn size="small" variant="text" :prepend-icon="copied === l.url ? 'tabler-check' : 'tabler-copy'" @click="copyLink(l.url)">{{ copied === l.url ? 'Copied' : 'Copy' }}</VBtn>
                <VBtn size="small" variant="text" prepend-icon="tabler-brand-whatsapp" :href="`https://wa.me/?text=${shareText(l.url)}`" target="_blank">WhatsApp</VBtn>
                <VBtn size="small" variant="text" prepend-icon="tabler-brand-teams" :href="`https://teams.microsoft.com/share?href=${encodeURIComponent(l.url)}&msgText=${shareText('')}`" target="_blank">Teams</VBtn>
                <VBtn size="small" variant="text" prepend-icon="tabler-mail" :href="`mailto:?subject=${encodeURIComponent(dashboard.name)}&body=${shareText(l.url)}`">Email</VBtn>
                <VBtn size="small" variant="text" color="error" @click="revokeLink(l.id)">Withdraw</VBtn>
              </div>
            </div>
          </template>
        </VCardText>
        <VCardActions><VSpacer /><VBtn variant="text" @click="share = false">Close</VBtn></VCardActions>
      </VCard>
    </VDialog>

    <!-- Comments -->
    <VNavigationDrawer v-model="commentsOpen" location="right" temporary width="400">
      <div class="pa-5 d-flex flex-column gap-3 h-100">
        <div class="d-flex align-center justify-space-between"><h3 class="text-h6">Comments</h3><VBtn icon="tabler-x" variant="text" size="small" @click="commentsOpen = false" /></div>
        <VSelect v-model="commentWidget" :items="[{ value: null, title: 'All comments' }, ...widgets.map(w => ({ value: w.i, title: w.title }))]" density="compact" variant="outlined" hide-details />
        <div class="bi-comments">
          <div v-for="c in shownComments" :key="c.id" class="bi-comment" :class="{ resolved: c.resolved }">
            <div class="bi-comment__head"><strong>{{ c.by }}</strong><span>{{ when(c.at) }}</span></div>
            <div v-if="!commentWidget" class="bi-comment__on">on {{ widgetTitle(c.widget_id) }}</div>
            <div class="bi-comment__body">{{ c.body }}</div>
            <button class="bi-comment__resolve" @click="resolveComment(c)">{{ c.resolved ? 'Reopen' : 'Resolve' }}</button>
          </div>
          <p v-if="!shownComments.length" class="text-caption text-medium-emphasis">No comments yet. Type @ and a name to bring someone in.</p>
        </div>
        <div class="bi-comment-new">
          <div v-if="mentionHint.length" class="bi-mentions"><button v-for="p in mentionHint" :key="p.email" @click="pickMention(p)">{{ p.name }} <small>{{ p.email }}</small></button></div>
          <VTextarea v-model="newComment" rows="3" auto-grow :placeholder="`Comment on ${widgetTitle(commentWidget)} — @name to notify someone`" density="compact" variant="outlined" hide-details @focus="people.length || axios.get('/sales/bi/people').then(r => (people = r.data))" />
          <div class="d-flex align-center justify-space-between mt-2"><span class="text-caption text-success">{{ commentNote }}</span><VBtn color="primary" size="small" :loading="sending" :disabled="!newComment.trim()" @click="sendComment">Post</VBtn></div>
        </div>
      </div>
    </VNavigationDrawer>

    <!-- Version history -->
    <VDialog v-model="historyOpen" max-width="560">
      <VCard title="Version history" subtitle="Each save keeps the layout before it; the last 30 are kept.">
        <VTable density="comfortable">
          <thead><tr><th>Version</th><th>Saved</th><th>By</th><th class="text-end">Visuals</th><th /></tr></thead>
          <tbody>
            <tr v-for="v in versions" :key="v.id"><td>v{{ v.version }}<div v-if="v.note" class="text-caption text-medium-emphasis">{{ v.note }}</div></td><td>{{ when(v.at) }}</td><td>{{ v.by }}</td><td class="text-end">{{ v.visuals }}</td>
              <td class="text-end"><VBtn v-if="canEdit" size="small" variant="text" @click="restoreVersion(v)">Restore</VBtn></td></tr>
            <tr v-if="!versions.length"><td colspan="5" class="text-medium-emphasis">No earlier versions yet — they appear after the next save.</td></tr>
          </tbody>
        </VTable>
        <VCardActions><VSpacer /><VBtn variant="text" @click="historyOpen = false">Close</VBtn></VCardActions>
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
.bi-header__actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.bi-link { border: 1px solid #EEEEF3; border-radius: 12px; padding: 8px 10px; }
.bi-link__url { font-size: 12px; font-family: monospace; color: #2B2B38; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bi-link__meta { font-size: 11px; color: #8A8A99; margin: 2px 0 2px; }
.bi-comments { flex: 1; min-height: 0; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; }
.bi-comment { border: 1px solid #EEEEF3; border-radius: 12px; padding: 10px 12px; background: #fff; }
.bi-comment.resolved { opacity: .55; }
.bi-comment__head { display: flex; justify-content: space-between; font-size: 12px; color: #8A8A99; } .bi-comment__head strong { color: #16161F; }
.bi-comment__on { font-size: 11px; color: #6A0DAD; margin-top: 2px; }
.bi-comment__body { font-size: 13px; color: #2B2B38; margin-top: 4px; white-space: pre-wrap; }
.bi-comment__resolve { border: 0; background: none; color: #6A0DAD; font-size: 11px; padding: 0; margin-top: 4px; cursor: pointer; }
.bi-mentions { display: flex; flex-direction: column; border: 1px solid #EEEEF3; border-radius: 10px; margin-bottom: 6px; overflow: hidden; }
.bi-mentions button { border: 0; background: #fff; text-align: left; padding: 6px 10px; font-size: 13px; cursor: pointer; } .bi-mentions button:hover { background: #F8F4FD; }
.bi-mentions small { color: #8A8A99; }
.bi-ask { display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid #E4D7F3; border-radius: 16px; padding: 8px 10px 8px 14px; margin: 0 8px 8px; box-shadow: 0 4px 16px rgba(106,13,173,.06); }
.bi-ask input { flex: 1; min-width: 0; border: 0; outline: 0; font-size: 14px; color: #16161F; background: transparent; }
.bi-ask-tips { display: flex; flex-wrap: wrap; gap: 6px; margin: 0 8px 12px; }
.bi-ask-tips button { border: 1px solid #EEEEF3; background: #fff; border-radius: 99px; padding: 3px 10px; font-size: 12px; color: #5B5B6E; cursor: pointer; }
.bi-ask-tips button:hover { border-color: #D9C8EE; color: #6A0DAD; }
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
