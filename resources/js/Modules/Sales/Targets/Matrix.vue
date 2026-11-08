<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps<{ rules: any[]; metrics: Record<string, string>; roles: string[]; users: { value: number; title: string }[] }>()
const blank = () => ({ name: '', metric: null as string | null, min_value: null as number | null, max_value: null as number | null, step: 1,
  approver_type: 'role', approver_role: props.roles[0] ?? null, approver_user_id: null as number | null, allow_creator: false, is_active: true })
const dialog = ref(false)
const editing = ref<any>(null)
const form = useForm(blank())
function open(r: any = null) { editing.value = r; Object.assign(form, r ? { ...blank(), ...r } : blank()); form.clearErrors(); dialog.value = true }
function save() {
  const opts = { preserveScroll: true, onSuccess: () => { dialog.value = false } }
  editing.value ? form.put(`/sales/targets/matrix/${editing.value.id}`, opts) : form.post('/sales/targets/matrix', opts)
}
const money = (n: any) => n === null || n === undefined ? '' : 'R ' + Number(n).toLocaleString('en-ZA', { maximumFractionDigits: 0 })
const range = (r: any) => r.min_value === null && r.max_value === null ? 'any value' : `${money(r.min_value) || 'R 0'} – ${money(r.max_value) || 'no limit'}`
const who = (r: any) => r.approver_type === 'role' ? `role: ${r.approver_role}` : r.approver_type === 'user' ? r.approver?.email : "creator's manager"
</script>

<template>
  <VContainer fluid class="pa-4 pa-sm-6">
    <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
      <div>
        <h3 class="text-h5 font-weight-bold mb-1">Target approvals matrix</h3>
        <p class="text-body-2 text-medium-emphasis mb-0">Each rule says which targets it covers (measure and value range) and who approves at which step. Steps run in order; any listed approver can sign a step. With no rules, any user allowed to approve targets — other than the creator — approves in one step.</p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" @click="open()">Add rule</VBtn>
    </div>
    <VCard elevation="0" border rounded="lg">
      <VTable>
        <thead><tr><th>Rule</th><th>Measure</th><th>Target value</th><th>Step</th><th>Approver</th><th>Creator may approve</th><th>Status</th><th /></tr></thead>
        <tbody>
          <tr v-for="r in rules" :key="r.id" :class="{ 'text-disabled': !r.is_active }">
            <td class="font-weight-medium">{{ r.name }}</td><td>{{ r.metric ? metrics[r.metric] : 'any' }}</td><td>{{ range(r) }}</td><td>{{ r.step }}</td><td>{{ who(r) }}</td>
            <td>{{ r.allow_creator ? 'Yes' : 'No' }}</td><td><VChip size="x-small" :color="r.is_active ? 'success' : 'default'" variant="tonal">{{ r.is_active ? 'On' : 'Off' }}</VChip></td>
            <td class="text-end"><VBtn icon="tabler-pencil" size="small" variant="text" @click="open(r)" /><VBtn v-if="r.is_active" icon="tabler-power" size="small" variant="text" color="error" @click="router.delete(`/sales/targets/matrix/${r.id}`, { preserveScroll: true })" /></td>
          </tr>
          <tr v-if="!rules.length"><td colspan="8" class="text-medium-emphasis text-center py-6">No rules — the default applies.</td></tr>
        </tbody>
      </VTable>
    </VCard>

    <VDialog v-model="dialog" max-width="640">
      <VCard :title="editing ? 'Edit rule' : 'Add rule'">
        <VCardText>
          <VRow dense>
            <VCol cols="12"><VTextField v-model="form.name" label="Rule name *" placeholder="e.g. Targets above R1m" density="compact" variant="outlined" :error-messages="form.errors.name" /></VCol>
            <VCol cols="12" md="6"><VSelect v-model="form.metric" :items="[{ value: null, title: 'Any measure' }, ...Object.entries(metrics).map(([v, t]) => ({ value: v, title: t }))]" label="Applies to measure" density="compact" variant="outlined" /></VCol>
            <VCol cols="6" md="3"><VTextField v-model.number="form.min_value" type="number" label="From value" density="compact" variant="outlined" /></VCol>
            <VCol cols="6" md="3"><VTextField v-model.number="form.max_value" type="number" label="To value" density="compact" variant="outlined" :error-messages="form.errors.max_value" /></VCol>
            <VCol cols="6" md="3"><VTextField v-model.number="form.step" type="number" min="1" label="Step" density="compact" variant="outlined" /></VCol>
            <VCol cols="12" md="9"><VSelect v-model="form.approver_type" :items="[{ value: 'role', title: 'Anyone with a role' }, { value: 'user', title: 'A named person' }, { value: 'creator_manager', title: 'The creator\'s manager (reports-to)' }]" label="Approver" density="compact" variant="outlined" /></VCol>
            <VCol v-if="form.approver_type === 'role'" cols="12"><VSelect v-model="form.approver_role" :items="roles" label="Role" density="compact" variant="outlined" :error-messages="form.errors.approver_role" hint="Only roles that hold the 'approve targets' permission are listed" persistent-hint /></VCol>
            <VCol v-if="form.approver_type === 'user'" cols="12"><VAutocomplete v-model="form.approver_user_id" :items="users" label="Person" density="compact" variant="outlined" :error-messages="form.errors.approver_user_id" /></VCol>
            <VCol cols="12"><VSwitch v-model="form.allow_creator" label="The person who created the target may approve this step (only if your policy allows it)" density="compact" hide-details inset color="warning" /></VCol>
            <VCol cols="12"><VSwitch v-model="form.is_active" label="Rule is on" density="compact" hide-details inset color="success" /></VCol>
          </VRow>
        </VCardText>
        <VCardActions><VSpacer /><VBtn variant="text" @click="dialog = false">Cancel</VBtn><VBtn color="primary" :loading="form.processing" @click="save">Save</VBtn></VCardActions>
      </VCard>
    </VDialog>
  </VContainer>
</template>
