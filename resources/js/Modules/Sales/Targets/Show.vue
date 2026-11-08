<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'

const props = defineProps<{ target: any; row: any; steps: { step: number; approvers: string[] }[]; canApprove: boolean; metrics: Record<string, string>; dimensions: Record<string, string>; options: Record<string, any[]> }>()
const t = props.target, p = props.row.performance
const isMoney = t.metric.includes('value')
const fmt = (n: any) => n === null || n === undefined ? '—' : (isMoney ? 'R ' : '') + Number(n).toLocaleString('en-ZA', { maximumFractionDigits: 2 })
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
  </VContainer>
</template>
