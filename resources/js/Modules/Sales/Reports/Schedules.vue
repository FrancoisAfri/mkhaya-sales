<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

type Opt = { value: string; title: string }
const props = defineProps<{ schedules: any[]; sources: Record<'standard' | 'saved' | 'dashboard', Opt[]>; periods: Opt[] }>()
const page = usePage<any>()

const days = [{ value: 1, title: 'Monday' }, { value: 2, title: 'Tuesday' }, { value: 3, title: 'Wednesday' }, { value: 4, title: 'Thursday' }, { value: 5, title: 'Friday' }, { value: 6, title: 'Saturday' }, { value: 7, title: 'Sunday' }]
const kinds = [{ value: 'standard', title: 'Standard report' }, { value: 'saved', title: 'Saved report' }, { value: 'dashboard', title: 'Dashboard (PDF summary)' }]
const dialog = ref(false)
const editing = ref<any>(null)
const recipientText = ref('')
const blank = () => ({ name: '', source: 'standard', source_ref: props.sources.standard[0]?.value ?? '', format: 'xlsx', period: 'last_month', frequency: 'monthly',
  weekday: 1 as number | null, month_day: 1 as number | null, send_time: '07:00', recipients: [] as string[], is_active: true })
const form = useForm(blank())

function open(s: any = null) {
  editing.value = s
  Object.assign(form, s ? { ...blank(), ...Object.fromEntries(Object.keys(blank()).map(k => [k, s[k] ?? (blank() as any)[k]])) } : blank())
  recipientText.value = (s?.recipients ?? [page.props.auth?.user?.email].filter(Boolean)).join(', ')
  form.clearErrors(); dialog.value = true
}
function save() {
  form.recipients = recipientText.value.split(/[,;\s]+/).map(x => x.trim()).filter(Boolean)
  if (!form.name) form.name = props.sources[form.source as 'standard'].find(o => o.value === form.source_ref)?.title ?? 'Sales report'
  const opts = { preserveScroll: true, onSuccess: () => { dialog.value = false } }
  editing.value ? form.put(`/sales/reports/schedules/${editing.value.id}`, opts) : form.post('/sales/reports/schedules', opts)
}
const toggle = (s: any) => router.put(`/sales/reports/schedules/${s.id}`, { only_active: true, is_active: !s.is_active }, { preserveScroll: true })
const sendNow = (s: any) => router.post(`/sales/reports/schedules/${s.id}/send`, {}, { preserveScroll: true })
const remove = (s: any) => confirm(`Remove “${s.name}”?`) && router.delete(`/sales/reports/schedules/${s.id}`, { preserveScroll: true })

const sourceTitle = (s: any) => props.sources[s.source as 'standard']?.find(o => o.value === String(s.source_ref))?.title ?? '(removed)'
const periodTitle = (v: string) => props.periods.find(p => p.value === v)?.title ?? v
const cadence = (s: any) => ({ daily: 'Every day', weekdays: 'Weekdays', weekly: `Every ${days.find(d => d.value === s.weekday)?.title}`, monthly: `Monthly on day ${s.month_day}` } as any)[s.frequency] + ` at ${s.send_time}`
const when = (x: string | null) => x ? new Date(x).toLocaleString('en-ZA', { dateStyle: 'medium', timeStyle: 'short' }) : 'not yet'
const refItems = computed(() => props.sources[form.source as 'standard'] ?? [])
</script>

<template>
  <VContainer fluid class="pa-4 pa-sm-6">
    <header class="pg-header">
      <div>
        <p class="pg-eyebrow"><a href="/sales/reports">Reports</a></p>
        <h1 class="pg-title">Email schedules</h1>
        <p class="pg-sub">Reports and dashboards emailed automatically, with the file attached. Times are South African time.</p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" @click="open()">New schedule</VBtn>
    </header>

    <VAlert v-if="page.props.errors?.schedule" type="error" variant="tonal" class="mb-4">{{ page.props.errors.schedule }}</VAlert>

    <VCard>
      <VTable v-if="schedules.length" hover>
        <thead><tr><th>Schedule</th><th>What</th><th>When</th><th>Recipients</th><th>Last sent</th><th class="text-center">On</th><th /></tr></thead>
        <tbody>
          <tr v-for="s in schedules" :key="s.id">
            <td><strong>{{ s.name }}</strong><div class="text-caption text-medium-emphasis">by {{ s.owner }}</div></td>
            <td>{{ sourceTitle(s) }}<div class="text-caption text-medium-emphasis">{{ periodTitle(s.period) }} · {{ s.format.toUpperCase() }}</div></td>
            <td>{{ cadence(s) }}</td>
            <td><span :title="s.recipients.join(', ')">{{ s.recipients[0] }}<span v-if="s.recipients.length > 1" class="text-medium-emphasis"> +{{ s.recipients.length - 1 }}</span></span></td>
            <td>
              <VChip v-if="s.last_status" size="x-small" :color="s.last_status === 'sent' ? 'success' : 'error'" variant="tonal" class="me-1">{{ s.last_status }}</VChip>{{ when(s.last_sent_at) }}
              <div v-if="s.last_error" class="text-caption text-error">{{ s.last_error }}</div>
            </td>
            <td class="text-center"><VSwitch :model-value="s.is_active" density="compact" hide-details inset color="primary" class="d-inline-flex" @update:model-value="toggle(s)" /></td>
            <td class="text-end">
              <VMenu location="bottom end">
                <template #activator="{ props: p }"><VBtn v-bind="p" icon="tabler-dots-vertical" variant="text" size="small" /></template>
                <VList density="compact">
                  <VListItem prepend-icon="tabler-send" title="Send now" @click="sendNow(s)" />
                  <VListItem prepend-icon="tabler-edit" title="Edit" @click="open(s)" />
                  <VListItem prepend-icon="tabler-trash" title="Remove" base-color="error" @click="remove(s)" />
                </VList>
              </VMenu>
            </td>
          </tr>
        </tbody>
      </VTable>
      <div v-else class="sch-empty">
        <VIcon icon="tabler-calendar-time" size="36" color="primary" />
        <h4>No schedules yet</h4>
        <p>Send the monthly sales report to management on the 1st, or a dashboard summary every Monday at 07:00.</p>
        <VBtn color="primary" variant="tonal" @click="open()">Create the first schedule</VBtn>
      </div>
    </VCard>

    <VDialog v-model="dialog" max-width="640">
      <VCard>
        <VCardTitle class="pa-5 pb-2">{{ editing ? 'Edit schedule' : 'New schedule' }}</VCardTitle>
        <VCardText class="pa-5 pt-2">
          <VRow>
            <VCol cols="12" sm="5"><VSelect v-model="form.source" :items="kinds" label="Send" variant="outlined" density="comfortable" hide-details @update:model-value="form.source_ref = refItems[0]?.value ?? ''; form.format = form.source === 'dashboard' ? 'pdf' : form.format" /></VCol>
            <VCol cols="12" sm="7"><VAutocomplete v-model="form.source_ref" :items="refItems" label="Report / dashboard" variant="outlined" density="comfortable" :error-messages="form.errors.source_ref" no-data-text="Nothing saved yet" /></VCol>
            <VCol cols="12"><VTextField v-model="form.name" label="Email subject / schedule name" placeholder="e.g. Monthly sales — management" variant="outlined" density="comfortable" :error-messages="form.errors.name" /></VCol>
            <VCol cols="12" sm="6"><VSelect v-model="form.period" :items="periods" label="Covering" variant="outlined" density="comfortable" hide-details /></VCol>
            <VCol cols="12" sm="6"><VSelect v-model="form.format" :items="[{ value: 'xlsx', title: 'Excel (.xlsx)' }, { value: 'csv', title: 'CSV' }, { value: 'pdf', title: 'PDF' }]" :disabled="form.source === 'dashboard'" label="File" variant="outlined" density="comfortable" hide-details /></VCol>
            <VCol cols="12" sm="4"><VSelect v-model="form.frequency" :items="[{ value: 'daily', title: 'Every day' }, { value: 'weekdays', title: 'Weekdays' }, { value: 'weekly', title: 'Weekly' }, { value: 'monthly', title: 'Monthly' }]" label="How often" variant="outlined" density="comfortable" hide-details /></VCol>
            <VCol v-if="form.frequency === 'weekly'" cols="12" sm="4"><VSelect v-model="form.weekday" :items="days" label="On" variant="outlined" density="comfortable" :error-messages="form.errors.weekday" /></VCol>
            <VCol v-if="form.frequency === 'monthly'" cols="12" sm="4"><VTextField v-model.number="form.month_day" type="number" min="1" max="31" label="Day of month" variant="outlined" density="comfortable" :error-messages="form.errors.month_day" hint="31 = last day" persistent-hint /></VCol>
            <VCol cols="12" sm="4"><VTextField v-model="form.send_time" type="time" label="At" variant="outlined" density="comfortable" :error-messages="form.errors.send_time" /></VCol>
            <VCol cols="12"><VTextarea v-model="recipientText" rows="2" auto-grow label="Recipients" placeholder="name@company.co.za, another@company.co.za" variant="outlined" density="comfortable"
                                       :error-messages="form.errors.recipients || Object.entries(form.errors).filter(([k]) => k.startsWith('recipients.')).map(([, v]) => v)[0]" hint="Separate addresses with commas" persistent-hint /></VCol>
          </VRow>
          <p class="text-caption text-medium-emphasis mt-4 mb-0">The report is built with your access, so recipients see what you would see. A short written summary goes in the email body.</p>
        </VCardText>
        <VCardActions class="pa-5 pt-0"><VSpacer /><VBtn variant="text" @click="dialog = false">Cancel</VBtn><VBtn color="primary" :loading="form.processing" @click="save">Save schedule</VBtn></VCardActions>
      </VCard>
    </VDialog>
  </VContainer>
</template>

<style scoped>
.pg-header { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
.pg-eyebrow { margin: 0 0 4px; font-size: 13px; font-weight: 600; color: #6A0DAD; } .pg-eyebrow a { color: inherit; text-decoration: none; }
.pg-title { margin: 0; font-size: 28px; font-weight: 700; letter-spacing: -.6px; color: #16161F; }
.pg-sub { margin: 4px 0 0; font-size: 13px; color: #8A8A99; }
.sch-empty { padding: 48px 24px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 6px; }
.sch-empty h4 { margin: 6px 0 0; font-size: 16px; } .sch-empty p { color: #77778A; max-width: 440px; margin: 0 0 10px; font-size: 13.5px; }
</style>
