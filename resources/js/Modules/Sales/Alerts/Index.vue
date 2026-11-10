<script setup lang="ts">
// A person's own alerts on any number (gap 3).
import { router, useForm, usePage } from '@inertiajs/vue3'
import { computed, onMounted, ref } from 'vue'
import RemoteSelect from '@/Components/Sales/RemoteSelect.vue'

type Opt = { value: string; title: string }
const props = defineProps<{ alerts: any[]; measures: Opt[]; periods: Opt[]; comparisons: Opt[]; options: Record<string, any[]>; prefill: Record<string, any> }>()
const page = usePage<any>()
const days = [{ value: 1, title: 'Monday' }, { value: 2, title: 'Tuesday' }, { value: 3, title: 'Wednesday' }, { value: 4, title: 'Thursday' }, { value: 5, title: 'Friday' }, { value: 6, title: 'Saturday' }, { value: 7, title: 'Sunday' }]
const dialog = ref(false)
const editing = ref<any>(null)
const blank = () => ({ name: '', measure: 'net_value', filters: {} as Record<string, any>, period: 'mtd', comparison: 'vs_last_year', operator: 'below', threshold: -10 as number | null,
  frequency: 'daily', weekday: 1 as number | null, run_time: '07:00', channels: ['in_app', 'email'], is_active: true })
const form = useForm(blank())
function open(a: any = null) {
  editing.value = a
  Object.assign(form, a ? { ...blank(), ...Object.fromEntries(Object.keys(blank()).map(k => [k, a[k] ?? (blank() as any)[k]])), threshold: Number(a.threshold) } : blank())
  form.clearErrors(); dialog.value = true
}
onMounted(() => { if (props.prefill?.measure) { open(); Object.assign(form, { measure: props.prefill.measure, name: props.prefill.name ?? '', filters: props.prefill.filters ?? {} }) } })
function save() {
  const opts = { preserveScroll: true, onSuccess: () => { dialog.value = false } }
  editing.value ? form.put(`/sales/alerts/${editing.value.uuid}`, opts) : form.post('/sales/alerts', opts)
}
const setFilter = (k: string, v: any) => { const f = { ...form.filters }; if (v === null || v === undefined || v === '') delete f[k]; else f[k] = v; form.filters = f }
const toggle = (a: any) => router.put(`/sales/alerts/${a.uuid}`, { only_active: true, is_active: !a.is_active }, { preserveScroll: true })
const test = (a: any) => router.post(`/sales/alerts/${a.uuid}/test`, {}, { preserveScroll: true })
const remove = (a: any) => confirm(`Remove “${a.name}”?`) && router.delete(`/sales/alerts/${a.uuid}`, { preserveScroll: true })

const label = (list: Opt[], v: string) => list.find(o => o.value === v)?.title ?? v
const isPct = computed(() => form.comparison !== 'value')
const unit = computed(() => (isPct.value ? '%' : form.measure === 'quantity' ? 'cases' : ['net_value', 'gross_value', 'discount', 'avg_selling_price'].includes(form.measure) ? 'R' : ''))
const sentence = (a: any) => `${label(props.measures, a.measure)} — ${label(props.comparisons, a.comparison).toLowerCase()} — ${a.operator} ${a.comparison === 'value' ? Number(a.threshold).toLocaleString('en-ZA') : a.threshold + '%'}`
const when = (s: string | null) => (s ? new Date(s).toLocaleString('en-ZA', { dateStyle: 'medium', timeStyle: 'short' }) : 'never')
const flash = computed(() => page.props.flash ?? {})
</script>

<template>
  <VContainer fluid class="pa-4 pa-sm-6">
    <header class="al-head">
      <div><p class="al-eyebrow">Targets</p><h1 class="al-title">My alerts</h1>
        <p class="al-sub">Be told when a number crosses a line — e.g. “Shoprite cases more than 10% below last week”. Checked at the time you choose.</p></div>
      <VBtn color="primary" prepend-icon="tabler-bell-plus" @click="open()">New alert</VBtn>
    </header>
    <VAlert v-if="flash.warning || flash.success" :type="flash.warning ? 'warning' : 'success'" variant="tonal" density="compact" class="mb-4">{{ flash.warning || flash.success }}</VAlert>

    <div class="al-grid">
      <div v-for="a in alerts" :key="a.uuid" class="al-card" :class="{ off: !a.is_active }">
        <div class="d-flex align-start justify-space-between gap-2">
          <div><h3>{{ a.name }}</h3><p>{{ sentence(a) }}</p></div>
          <VSwitch :model-value="a.is_active" density="compact" hide-details inset color="primary" @update:model-value="toggle(a)" />
        </div>
        <div class="al-meta">
          <span><VIcon icon="tabler-calendar" size="14" /> {{ label(periods, a.period) }}</span>
          <span><VIcon icon="tabler-clock" size="14" /> {{ a.frequency === 'weekly' ? days.find(d => d.value === a.weekday)?.title : 'Daily' }} {{ a.run_time }}</span>
          <span><VIcon icon="tabler-send" size="14" /> {{ a.channels.map((c: string) => c === 'email' ? 'Email' : 'In the app').join(' + ') }}</span>
          <span v-if="Object.keys(a.filters || {}).length"><VIcon icon="tabler-filter" size="14" /> {{ Object.keys(a.filters).length }} filter(s)</span>
        </div>
        <div class="al-last">Last checked {{ when(a.last_checked_at) }}<template v-if="a.last_value !== null"> · last figure {{ Number(a.last_value).toLocaleString('en-ZA') }}{{ a.comparison === 'value' ? '' : '%' }}</template>
          <template v-if="a.last_triggered_at"> · <b class="text-warning">fired {{ when(a.last_triggered_at) }}</b></template></div>
        <div class="d-flex gap-1 mt-2"><VBtn size="small" variant="tonal" prepend-icon="tabler-player-play" @click="test(a)">Check now</VBtn>
          <VBtn size="small" variant="text" @click="open(a)">Edit</VBtn><VBtn size="small" variant="text" color="error" @click="remove(a)">Remove</VBtn></div>
      </div>
      <div v-if="!alerts.length" class="al-empty"><VIcon icon="tabler-bell-ringing" size="36" color="primary" /><h4>No alerts yet</h4>
        <p>Create one here, or open any dashboard visual's menu and choose “Alert me on this”.</p><VBtn color="primary" variant="tonal" @click="open()">Create the first alert</VBtn></div>
    </div>

    <VDialog v-model="dialog" max-width="640">
      <VCard>
        <VCardTitle class="pa-5 pb-2">{{ editing ? 'Edit alert' : 'New alert' }}</VCardTitle>
        <VCardText class="pa-5 pt-2">
          <VRow>
            <VCol cols="12"><VTextField v-model="form.name" label="Name" placeholder="Shoprite cases dropping" variant="outlined" density="comfortable" :error-messages="form.errors.name" /></VCol>
            <VCol cols="12" sm="6"><VSelect v-model="form.measure" :items="measures" label="Watch" variant="outlined" density="comfortable" hide-details /></VCol>
            <VCol cols="12" sm="6"><VSelect v-model="form.period" :items="periods" label="Over" variant="outlined" density="comfortable" hide-details /></VCol>
            <VCol cols="12" sm="6"><VSelect v-model="form.comparison" :items="comparisons" label="Look at" variant="outlined" density="comfortable" hide-details /></VCol>
            <VCol cols="6" sm="3"><VSelect v-model="form.operator" :items="[{ value: 'below', title: 'Below' }, { value: 'above', title: 'Above' }]" label="Alert when" variant="outlined" density="comfortable" hide-details /></VCol>
            <VCol cols="6" sm="3"><VTextField v-model.number="form.threshold" type="number" :label="`Line (${unit})`" variant="outlined" density="comfortable" :error-messages="form.errors.threshold" /></VCol>
            <VCol cols="12"><div class="text-subtitle-2 mb-1">Only for (optional)</div></VCol>
            <VCol cols="12" sm="6"><RemoteSelect :model-value="form.filters.customer_id ?? null" resource="customers" :initial="options.customers" label="Customer" @update:model-value="v => setFilter('customer_id', v)" /></VCol>
            <VCol cols="12" sm="6"><RemoteSelect :model-value="form.filters.product_id ?? null" resource="products" :initial="options.products" label="Product" @update:model-value="v => setFilter('product_id', v)" /></VCol>
            <VCol v-for="[k, o, l] in [['customer_group_id', 'customer_groups', 'Customer group'], ['category_id', 'categories', 'Category'], ['region_id', 'regions', 'Region'], ['brand_id', 'brands', 'Brand']]" :key="k" cols="12" sm="6">
              <VAutocomplete :model-value="form.filters[k] ? Number(form.filters[k]) : null" :items="options[o] ?? []" :label="l" clearable variant="outlined" density="comfortable" hide-details @update:model-value="v => setFilter(k, v)" /></VCol>
            <VCol cols="6" sm="4"><VSelect v-model="form.frequency" :items="[{ value: 'daily', title: 'Every day' }, { value: 'weekly', title: 'Weekly' }]" label="Check" variant="outlined" density="comfortable" hide-details /></VCol>
            <VCol v-if="form.frequency === 'weekly'" cols="6" sm="4"><VSelect v-model="form.weekday" :items="days" label="On" variant="outlined" density="comfortable" hide-details /></VCol>
            <VCol cols="6" sm="4"><VTextField v-model="form.run_time" type="time" label="At" variant="outlined" density="comfortable" hide-details /></VCol>
            <VCol cols="12"><VChipGroup v-model="form.channels" multiple column><VChip value="in_app" filter>In the app</VChip><VChip value="email" filter>Email</VChip></VChipGroup>
              <div v-if="form.errors.channels" class="text-error text-caption">{{ form.errors.channels }}</div></VCol>
          </VRow>
        </VCardText>
        <VCardActions class="pa-5 pt-0"><VSpacer /><VBtn variant="text" @click="dialog = false">Cancel</VBtn><VBtn color="primary" :loading="form.processing" @click="save">Save alert</VBtn></VCardActions>
      </VCard>
    </VDialog>
  </VContainer>
</template>

<style scoped>
.al-head { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
.al-eyebrow { margin: 0 0 4px; font-size: 13px; font-weight: 600; color: #6A0DAD; }
.al-title { margin: 0; font-size: 28px; font-weight: 700; letter-spacing: -.6px; color: #16161F; }
.al-sub { margin: 4px 0 0; font-size: 13px; color: #8A8A99; max-width: 680px; }
.al-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 16px; }
.al-card { background: #fff; border: 1px solid #EEEEF3; border-radius: 18px; padding: 18px 20px; }
.al-card.off { opacity: .6; }
.al-card h3 { margin: 0; font-size: 16px; font-weight: 700; color: #16161F; } .al-card p { margin: 2px 0 0; font-size: 13px; color: #5B5B6E; }
.al-meta { display: flex; flex-wrap: wrap; gap: 6px 14px; margin-top: 10px; font-size: 12px; color: #77778A; }
.al-meta span { display: inline-flex; align-items: center; gap: 4px; }
.al-last { margin-top: 8px; font-size: 12px; color: #8A8A99; }
.al-empty { grid-column: 1 / -1; background: #fff; border: 1px dashed #D9C8EE; border-radius: 18px; padding: 40px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 6px; }
.al-empty h4 { margin: 4px 0 0; } .al-empty p { margin: 0 0 8px; color: #77778A; font-size: 13.5px; }
</style>
