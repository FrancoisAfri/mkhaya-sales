<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps<{
  targets: { data: any[]; total: number; current_page: number; per_page: number }; filters: Record<string, any>
  metrics: Record<string, string>; methods: Record<string, string>; periods: string[]; dimensions: Record<string, string>
  options: Record<string, { value: number; title: string }[]>; canCreate: boolean
}>()

const optKey: Record<string, string> = { customer_id: 'customers', customer_group_id: 'customer_groups', product_id: 'products', category_id: 'categories', brand_id: 'brands',
  region_id: 'regions', business_unit_id: 'business_units', sales_team_id: 'sales_teams', salesperson_id: 'salespeople', distribution_centre_id: 'distribution_centres' }
const today = new Date()
const iso = (d: Date) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10)
const blank = () => ({ name: '', metric: 'net_value', method: 'fixed', period_type: 'monthly', start_date: iso(new Date(today.getFullYear(), today.getMonth(), 1)),
  end_date: iso(new Date(today.getFullYear(), today.getMonth() + 1, 0)), target_value: null as number | null, target_value_max: null as number | null,
  dimensions: {} as Record<string, number | null>, notes: '', submit_now: false, formula: '', weight: 1 })
const dialog = ref(false)
const editing = ref<any>(null)
const form = useForm(blank())
function open(t: any = null) {
  editing.value = t
  Object.assign(form, t ? { ...blank(), ...t, target_value: t.performance.target, dimensions: { ...(t.dimensions ?? {}) }, submit_now: false } : blank())
  form.clearErrors(); dialog.value = true
}
function save(submit: boolean) {
  form.submit_now = submit
  const opts = { preserveScroll: true, onSuccess: () => { dialog.value = false } }
  editing.value ? form.put(`/sales/targets/${editing.value.uuid}`, opts) : form.post('/sales/targets', opts)
}
const fmt = (t: any, n: any) => t.method === 'growth' ? `${n ?? 0}%` : t.method === 'composite' ? `${n ?? 0} pts` : t.metric.includes('value') ? 'R ' + Number(n ?? 0).toLocaleString('en-ZA', { maximumFractionDigits: 0 }) : Number(n ?? 0).toLocaleString('en-ZA', { maximumFractionDigits: 2 })
const dimText = (t: any) => Object.entries(t.dimensions ?? {}).map(([k, v]) => `${props.dimensions[k]}: ${props.options[optKey[k]]?.find(o => o.value === v)?.title ?? v}`).join(', ') || 'Whole organisation'
const lifeColor: Record<string, string> = { draft: 'default', submitted: 'info', 'revision submitted': 'info', approved: 'primary', active: 'success', 'active (revised)': 'success', rejected: 'error', archived: 'default', completed: 'secondary' }
const perfColor: Record<string, string> = { achieved: 'success', on_track: 'info', at_risk: 'warning', behind: 'error', breached: 'error', not_started: 'default' }
</script>

<template>
  <VContainer fluid class="pa-4 pa-sm-6">
    <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
      <div><h3 class="text-h5 font-weight-bold mb-1">Targets</h3><p class="text-body-2 text-medium-emphasis mb-0">Draft → submitted → approved → active. Approvers come from the <a href="/sales/targets/matrix">approvals matrix</a>.</p></div>
      <div class="d-flex gap-2">
        <VSelect :model-value="filters.status ?? null" :items="['draft', 'submitted', 'approved', 'rejected', 'archived']" label="Status" clearable density="compact" variant="outlined" hide-details style="width: 180px"
                 @update:model-value="v => router.get('/sales/targets', { status: v ?? undefined }, { preserveState: true })" />
        <VBtn v-if="canCreate" color="primary" prepend-icon="tabler-plus" @click="open()">New target</VBtn>
      </div>
    </div>
    <VCard elevation="0" border rounded="lg">
      <VTable density="comfortable">
        <thead><tr><th>Target</th><th>Applies to</th><th>Period</th><th class="text-end">Target</th><th class="text-end">Actual</th><th>Achievement</th><th>Status</th><th /></tr></thead>
        <tbody>
          <tr v-for="t in targets.data" :key="t.uuid">
            <td><a :href="`/sales/targets/${t.uuid}`" class="font-weight-medium">{{ t.name }}</a><div class="text-caption text-medium-emphasis">{{ metrics[t.metric] }} · v{{ t.version }}</div></td>
            <td class="text-caption">{{ dimText(t) }}</td>
            <td class="text-caption">{{ t.start_date }} → {{ t.end_date }}</td>
            <td class="text-end">{{ fmt(t, t.performance.target) }}</td><td class="text-end">{{ fmt(t, t.performance.actual) }}</td>
            <td style="min-width: 140px"><VProgressLinear :model-value="Math.min(100, t.performance.achievement ?? 0)" :color="perfColor[t.performance.status]" height="14" rounded><small>{{ t.performance.achievement ?? '—' }}%</small></VProgressLinear></td>
            <td><VChip size="x-small" :color="lifeColor[t.lifecycle]" variant="tonal">{{ t.lifecycle }}</VChip></td>
            <td class="text-end text-no-wrap">
              <VBtn v-if="t.can_edit" icon="tabler-pencil" size="small" variant="text" @click="open(t)" />
              <VBtn v-if="t.can_submit" size="small" variant="tonal" @click="router.post(`/sales/targets/${t.uuid}/submit`, {}, { preserveScroll: true })">Submit</VBtn>
              <VBtn v-if="t.can_approve" size="small" color="success" variant="tonal" :href="`/sales/targets/${t.uuid}`">Review</VBtn>
            </td>
          </tr>
          <tr v-if="!targets.data.length"><td colspan="8" class="text-medium-emphasis text-center py-6">No targets yet.</td></tr>
        </tbody>
      </VTable>
    </VCard>

    <VDialog v-model="dialog" max-width="820" scrollable>
      <VCard :title="editing ? 'Edit target' : 'New target'">
        <VCardText>
          <VRow dense>
            <VCol cols="12"><VTextField v-model="form.name" label="Name *" density="compact" variant="outlined" :error-messages="form.errors.name" /></VCol>
            <VCol cols="12" md="4"><VSelect v-model="form.metric" :items="Object.entries(metrics).map(([v, t]) => ({ value: v, title: t }))" label="What is measured" density="compact" variant="outlined" /></VCol>
            <VCol cols="12" md="4"><VSelect v-model="form.method" :items="Object.entries(methods).map(([v, t]) => ({ value: v, title: t }))" label="Rule" density="compact" variant="outlined" /></VCol>
            <VCol cols="12" md="4"><VSelect v-model="form.period_type" :items="periods.map(p => ({ value: p, title: p.replace('_', ' ') }))" label="Period" density="compact" variant="outlined" /></VCol>
            <VCol v-if="form.metric === 'formula'" cols="12"><VTextField v-model="form.formula" label="Formula *" placeholder="net_value / quantity" density="compact" variant="outlined" :error-messages="form.errors.formula"
              hint="Numbers, + - * / ( ) and: net_value, gross_value, quantity, discount, active_customers, transactions" persistent-hint /></VCol>
            <VAlert v-if="form.method === 'growth'" type="info" variant="tonal" density="compact" class="mx-2 mb-2">Enter the target as a % growth on the same period last year, e.g. 8 for +8%.</VAlert>
            <VAlert v-if="form.method === 'composite'" type="info" variant="tonal" density="compact" class="mx-2 mb-2">A composite is scored out of 100 from its child targets (allocate or link them, each with a weight). Enter 100 as the target.</VAlert>
            <VCol cols="6" md="3"><VTextField v-model="form.start_date" type="date" label="From *" density="compact" variant="outlined" :error-messages="form.errors.start_date" /></VCol>
            <VCol cols="6" md="3"><VTextField v-model="form.end_date" type="date" label="To *" density="compact" variant="outlined" :error-messages="form.errors.end_date" /></VCol>
            <VCol cols="6" md="3"><VTextField v-model.number="form.target_value" type="number" :label="form.method === 'range' ? 'From value *' : 'Target value *'" density="compact" variant="outlined" :error-messages="form.errors.target_value" /></VCol>
            <VCol v-if="form.method === 'range'" cols="6" md="3"><VTextField v-model.number="form.target_value_max" type="number" label="To value *" density="compact" variant="outlined" :error-messages="form.errors.target_value_max" /></VCol>
            <VCol cols="12"><div class="text-subtitle-2 mt-2">Applies to (leave empty for the whole organisation; combine for e.g. a category in a region)</div></VCol>
            <VCol v-for="(label, key) in dimensions" :key="key" cols="12" md="4">
              <VAutocomplete v-model="form.dimensions[key]" :items="options[optKey[key]] ?? []" :label="label" clearable density="compact" variant="outlined" hide-details />
            </VCol>
            <VCol cols="6" md="3"><VTextField v-model.number="form.weight" type="number" label="Weight (in a composite)" density="compact" variant="outlined" hide-details /></VCol>
            <VCol cols="12"><VTextarea v-model="form.notes" label="Notes" rows="2" density="compact" variant="outlined" class="mt-3" /></VCol>
          </VRow>
        </VCardText>
        <VCardActions><VSpacer /><VBtn variant="text" @click="dialog = false">Cancel</VBtn><VBtn variant="tonal" :loading="form.processing" @click="save(false)">Save draft</VBtn>
          <VBtn v-if="!editing" color="primary" :loading="form.processing" @click="save(true)">Save and submit</VBtn></VCardActions>
      </VCard>
    </VDialog>
  </VContainer>
</template>
