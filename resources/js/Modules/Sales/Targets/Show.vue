<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'

import { computed } from 'vue'
import RemoteSelect from '@/Components/Sales/RemoteSelect.vue'

const props = defineProps<{ target: any; row: any; steps: { step: number; approvers: string[] }[]; canApprove: boolean; metrics: Record<string, string>; dimensions: Record<string, string>; options: Record<string, any[]>
  breakdown: any[]; breakdownBy: string; children: any[]; reconciliation: { children: number; allocated: number; parent: number; difference: number; balanced: boolean } | null; canAllocate: boolean }>()
const optKey: Record<string, string> = { customer_id: 'customers', customer_group_id: 'customer_groups', product_id: 'products', category_id: 'categories', brand_id: 'brands',
  region_id: 'regions', business_unit_id: 'business_units', sales_team_id: 'sales_teams', salesperson_id: 'salespeople', distribution_centre_id: 'distribution_centres' }
const alloc = useForm({ dimension: 'region_id', method: 'historical', members: [] as number[], given: {} as Record<number, number | null> })
const allocDims = computed(() => Object.entries(props.dimensions).filter(([k]) => !(k in (props.target.dimensions ?? {}))).map(([v, t]) => ({ value: v, title: t })))
const memberItems = computed(() => props.options[optKey[alloc.dimension]] ?? [])
const givenSum = computed(() => alloc.members.reduce((a, id) => a + Number(alloc.given[id] ?? 0), 0))
function allocateAll() { alloc.members = memberItems.value.map((o: any) => o.value) }
const t = props.target, p = props.row.performance
const byOptions = [['week', 'Weeks'], ['month', 'Months'], ['quarter', 'Quarters'], ['year_to_date', 'Year to date']]
const showBy = (by: string) => router.get(location.pathname, { by }, { preserveScroll: true, preserveState: true, only: ['breakdown', 'breakdownBy'] })
const achColor = (a: number | null) => a === null ? undefined : a >= 100 ? '#2E7D32' : a >= 90 ? '#E4A800' : '#C62828'
const isMoney = t.metric.includes('value') && !['growth', 'composite'].includes(t.method)
const unit = t.method === 'growth' ? '%' : t.method === 'composite' ? ' pts' : ''
const fmt = (n: any) => n === null || n === undefined ? '—' : (isMoney ? 'R ' : '') + Number(n).toLocaleString('en-ZA', { maximumFractionDigits: isMoney ? 0 : 2 }) + unit
const decide = useForm({ decision: 'approve', comment: '' })
const revise = useForm({ target_value: t.target_value, target_value_max: t.target_value_max, reason: '' })
const perfColor: Record<string, string> = { achieved: 'success', on_track: 'info', at_risk: 'warning', behind: 'error', breached: 'error', not_started: 'default' }
const when = (s: string) => new Date(s).toLocaleString('en-ZA', { dateStyle: 'medium', timeStyle: 'short' })
function send(decision: string) { decide.decision = decision; decide.post(`/sales/targets/${t.uuid}/decide`, { preserveScroll: true }) }
</script>

<template>
  <VContainer fluid class="pa-4 pa-sm-6">
    <VBtn variant="text" size="small" prepend-icon="tabler-arrow-left" href="/sales/targets" class="mb-2">Targets</VBtn>
    <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
      <div><h3 class="text-h5 font-weight-bold mb-1">{{ t.name }}</h3>
        <p class="text-body-2 text-medium-emphasis mb-0">{{ metrics[t.metric] }} · {{ t.start_date.slice(0, 10) }} → {{ t.end_date.slice(0, 10) }} · version {{ t.version }} · created by {{ t.creator?.email }}</p></div>
      <VChip color="primary" variant="tonal">{{ row.lifecycle }}</VChip>
    </div>

    <VRow class="mb-2">
      <VCol v-for="k in [['Target', fmt(p.target)], ['Actual', fmt(p.actual)], ['Achievement', (p.achievement ?? '—') + '%'], ['Variance', fmt(p.variance)], ['Remaining', fmt(p.remaining)], ['Needed per day', fmt(p.required_daily)], ['Forecast', fmt(p.forecast)]]" :key="k[0]" cols="6" md="">
        <VCard elevation="0" border rounded="lg"><VCardText class="py-3"><div class="text-caption text-medium-emphasis">{{ k[0] }}</div><div class="text-h6">{{ k[1] }}</div></VCardText></VCard>
      </VCol>
    </VRow>
    <VProgressLinear :model-value="Math.min(100, p.achievement ?? 0)" :color="perfColor[p.status]" height="22" rounded class="mb-6"><strong>{{ p.status.replace('_', ' ') }} · {{ p.days_left }} days left</strong></VProgressLinear>

    <VCard v-if="breakdown.length" class="mb-6">
      <div class="d-flex flex-wrap align-center justify-space-between gap-3 pa-5 pb-2">
        <div><div class="text-subtitle-1 font-weight-bold">Target by period</div><div class="text-caption text-medium-emphasis">The target spread evenly over its days; year to date adds each month to the ones before it.</div></div>
        <VBtnToggle :model-value="breakdownBy" mandatory density="compact" variant="outlined" color="primary" divided>
          <VBtn v-for="[k, l] in byOptions" :key="k" :value="k" size="small" @click="showBy(k)">{{ l }}</VBtn>
        </VBtnToggle>
      </div>
      <VTable density="comfortable" hover>
        <thead><tr><th>Period</th><th class="text-end">Target</th><th class="text-end">Actual</th><th class="text-end">Variance</th><th style="width: 28%">Achievement</th></tr></thead>
        <tbody>
          <tr v-for="r in breakdown" :key="r.from">
            <td>{{ r.label }}</td><td class="text-end">{{ fmt(r.target) }}</td><td class="text-end">{{ fmt(r.actual) }}</td>
            <td class="text-end" :style="{ color: r.variance === null ? undefined : r.variance >= 0 ? '#2E7D32' : '#C62828' }">{{ fmt(r.variance) }}</td>
            <td><div v-if="r.achievement !== null" class="d-flex align-center gap-2"><VProgressLinear :model-value="Math.min(100, r.achievement)" :color="achColor(r.achievement)" height="8" rounded /><span class="text-caption" style="min-width: 48px">{{ r.achievement }}%</span></div><span v-else class="text-caption text-medium-emphasis">not started</span></td>
          </tr>
        </tbody>
      </VTable>
    </VCard>

    <VRow>
      <VCol cols="12" md="6">
        <VCard elevation="0" border rounded="lg" class="mb-4" title="Approval route" subtitle="From the approvals matrix">
          <VCardText>
            <VTimeline density="compact" side="end" truncate-line="both">
              <VTimelineItem v-for="s in steps" :key="s.step" :dot-color="t.current_step >= s.step ? 'success' : 'grey'" size="small">
                <div class="text-body-2"><strong>Step {{ s.step }}</strong> — {{ s.approvers.join(' or ') }}</div>
              </VTimelineItem>
              <VTimelineItem v-if="!steps.length" dot-color="grey" size="small"><div class="text-body-2">Any user who may approve targets, other than the creator.</div></VTimelineItem>
            </VTimeline>
            <template v-if="canApprove">
              <VTextarea v-model="decide.comment" label="Comment (required to reject)" rows="2" density="compact" variant="outlined" :error-messages="decide.errors.comment" class="mt-2" />
              <div class="d-flex gap-2"><VBtn color="success" :loading="decide.processing" @click="send('approve')">Approve</VBtn><VBtn color="error" variant="tonal" :loading="decide.processing" @click="send('reject')">Reject</VBtn></div>
            </template>
          </VCardText>
        </VCard>
        <VCard elevation="0" border rounded="lg" title="Decisions">
          <VTable density="compact"><tbody>
            <tr v-for="a in target.approvals" :key="a.id"><td>v{{ a.version }} step {{ a.step }}</td><td><VChip size="x-small" :color="a.decision === 'approved' ? 'success' : 'error'" variant="tonal">{{ a.decision }}</VChip></td><td>{{ a.decider?.email }}</td><td class="text-caption">{{ a.comment }}</td><td class="text-caption">{{ when(a.decided_at) }}</td></tr>
            <tr v-if="!target.approvals.length"><td class="text-medium-emphasis">No decisions yet.</td></tr>
          </tbody></VTable>
        </VCard>
      </VCol>
      <VCol cols="12" md="6">
        <VCard v-if="['approved', 'rejected'].includes(t.status)" elevation="0" border rounded="lg" class="mb-4" title="Revise target" subtitle="The current value is kept in the history; the revision is approved again">
          <VCardText class="d-flex flex-column gap-3">
            <VTextField v-model.number="revise.target_value" type="number" label="New target value" density="compact" variant="outlined" hide-details />
            <VTextarea v-model="revise.reason" label="Reason for the change" rows="2" density="compact" variant="outlined" :error-messages="revise.errors.reason" />
            <VBtn variant="tonal" :loading="revise.processing" @click="revise.post(`/sales/targets/${t.uuid}/revise`, { preserveScroll: true })">Submit revision</VBtn>
          </VCardText>
        </VCard>
        <VCard elevation="0" border rounded="lg" title="Revision history">
          <VTable density="compact"><thead><tr><th>From version</th><th class="text-end">Previous</th><th class="text-end">New</th><th>Reason</th><th>By</th></tr></thead><tbody>
            <tr v-for="r in target.revisions" :key="r.id"><td>v{{ r.from_version }}</td><td class="text-end">{{ fmt(r.previous_value) }}</td><td class="text-end">{{ fmt(r.new_value) }}</td><td class="text-caption">{{ r.reason }}</td><td class="text-caption">{{ r.reviser?.email }}<br>{{ when(r.created_at) }}</td></tr>
            <tr v-if="!target.revisions.length"><td colspan="5" class="text-medium-emphasis">Never revised.</td></tr>
          </tbody></VTable>
        </VCard>
        <VBtn v-if="t.status !== 'archived'" variant="text" color="error" class="mt-3" @click="router.post(`/sales/targets/${t.uuid}/archive`)">Archive target</VBtn>
      </VCol>
    </VRow>

    <VRow class="mt-2">
      <VCol cols="12" md="5">
        <VCard v-if="canAllocate" elevation="0" border rounded="lg" title="Allocate to child targets" subtitle="Children are created as drafts and always add up to this target">
          <VCardText class="d-flex flex-column gap-3">
            <VAlert v-if="target.parent" type="info" variant="tonal" density="compact">Part of <a :href="`/sales/targets/${target.parent.uuid}`">{{ target.parent.name }}</a></VAlert>
            <VSelect v-model="alloc.dimension" :items="allocDims" label="Split by" density="compact" variant="outlined" hide-details @update:model-value="alloc.members = []; alloc.given = {}" />
            <VSelect v-model="alloc.method" :items="[{ value: 'historical', title: 'Last year\'s sales mix (same period)' }, { value: 'equal', title: 'Equal shares' }, { value: 'percentage', title: 'Percentages I enter' }, { value: 'manual', title: 'Values I enter' }]" label="Method" density="compact" variant="outlined" hide-details />
            <RemoteSelect v-if="['customer_id', 'product_id'].includes(alloc.dimension)" v-model="alloc.members" :resource="alloc.dimension === 'customer_id' ? 'customers' : 'products'" label="Allocate to" multiple />
            <VAutocomplete v-else v-model="alloc.members" :items="memberItems" label="Allocate to" multiple chips closable-chips density="compact" variant="outlined" hide-details>
              <template #append><VBtn size="x-small" variant="text" @click="allocateAll">All</VBtn></template>
            </VAutocomplete>
            <template v-if="['percentage', 'manual'].includes(alloc.method)">
              <VTextField v-for="id in alloc.members" :key="id" v-model.number="alloc.given[id]" type="number" :label="memberItems.find((o: any) => o.value === id)?.title + (alloc.method === 'percentage' ? ' (%)' : '')" density="compact" variant="outlined" hide-details />
              <div class="text-caption" :class="Math.abs(givenSum - (alloc.method === 'percentage' ? 100 : t.target_value)) < 0.01 ? 'text-success' : 'text-error'">Total {{ givenSum }} of {{ alloc.method === 'percentage' ? 100 : fmt(t.target_value) }}</div>
            </template>
            <VAlert v-if="$page.props.errors?.target" type="error" variant="tonal" density="compact">{{ $page.props.errors.target }}</VAlert>
            <VBtn color="primary" :disabled="!alloc.members.length" :loading="alloc.processing" @click="alloc.post(`/sales/targets/${t.uuid}/allocate`, { preserveScroll: true })">Create child targets</VBtn>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="7">
        <VCard v-if="children.length" elevation="0" border rounded="lg" title="Child targets">
          <VCardText v-if="reconciliation" class="pb-0">
            <VChip :color="reconciliation.balanced ? 'success' : 'error'" variant="tonal" size="small">{{ reconciliation.balanced ? 'Adds up to the parent' : `Off by ${fmt(reconciliation.difference)}` }} · {{ fmt(reconciliation.allocated) }} allocated</VChip>
          </VCardText>
          <VTable density="compact"><thead><tr><th>Child</th><th class="text-end">Target</th><th class="text-end">Actual</th><th class="text-end">Achievement</th><th>Status</th></tr></thead><tbody>
            <tr v-for="c in children" :key="c.uuid"><td><a :href="`/sales/targets/${c.uuid}`">{{ c.name }}</a></td><td class="text-end">{{ fmt(c.performance.target) }}</td><td class="text-end">{{ fmt(c.performance.actual) }}</td>
              <td class="text-end">{{ c.performance.achievement ?? '—' }}%</td><td><VChip size="x-small" variant="tonal">{{ c.lifecycle }}</VChip></td></tr>
          </tbody></VTable>
        </VCard>
      </VCol>
    </VRow>
  </VContainer>
</template>
