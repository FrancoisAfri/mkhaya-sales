<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'

const props = defineProps<{ settings: any }>()
const form = useForm(JSON.parse(JSON.stringify(props.settings)))
</script>

<template>
  <VContainer fluid class="pa-4 pa-sm-6" style="max-width: 860px">
    <h3 class="text-h5 font-weight-bold mb-1">Alert settings</h3>
    <p class="text-body-2 text-medium-emphasis mb-4">Checked once a day. Alerts go in-app and by email to users allowed to see them; the same unresolved issue is not repeated (at-risk targets at most weekly, a target achieved once).</p>
    <VCard elevation="0" border rounded="lg">
      <VCardText class="d-flex flex-column gap-4">
        <VTextField v-model="form.run_time" type="time" label="Check every day at (SAST)" density="compact" variant="outlined" style="max-width: 220px" :error-messages="form.errors.run_time" />
        <VDivider />
        <VSwitch v-model="form.target_achieved.enabled" label="Target achieved" color="success" inset hide-details />
        <div class="d-flex align-center gap-4 flex-wrap">
          <VSwitch v-model="form.target_at_risk.enabled" label="Target at risk or behind" color="warning" inset hide-details />
          <VTextField v-model.number="form.target_at_risk.threshold" type="number" suffix="%" label="when the forecast is below" density="compact" variant="outlined" hide-details style="max-width: 220px" :disabled="!form.target_at_risk.enabled" />
        </div>
        <div class="d-flex align-center gap-4 flex-wrap">
          <VSwitch v-model="form.unusual_movement.enabled" label="Unusual daily sales" color="info" inset hide-details />
          <VTextField v-model.number="form.unusual_movement.threshold" type="number" suffix="%" label="when a day differs from the usual same weekday by" density="compact" variant="outlined" hide-details style="max-width: 320px" :disabled="!form.unusual_movement.enabled" />
        </div>
        <p class="text-caption text-medium-emphasis mb-0">Import, mailbox and missing-report alerts are always on.</p>
      </VCardText>
      <VCardActions><VSpacer /><VBtn color="primary" :loading="form.processing" @click="form.post('/sales/targets/alerts', { preserveScroll: true })">Save</VBtn></VCardActions>
    </VCard>
  </VContainer>
</template>
