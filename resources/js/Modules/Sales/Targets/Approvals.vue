<script setup lang="ts">
import { router } from '@inertiajs/vue3'
defineProps<{ targets: any[]; metrics: Record<string, string> }>()
const fmt = (t: any, n: any) => (t.metric.includes('value') ? 'R ' : '') + Number(n ?? 0).toLocaleString('en-ZA', { maximumFractionDigits: 0 })
</script>

<template>
  <VContainer fluid class="pa-4 pa-sm-6">
    <h3 class="text-h5 font-weight-bold mb-1">Awaiting my approval</h3>
    <p class="text-body-2 text-medium-emphasis mb-4">Targets at a step of the approvals matrix where you are an approver. You never see your own.</p>
    <VCard elevation="0" border rounded="lg">
      <VTable>
        <thead><tr><th>Target</th><th>Measure</th><th>Period</th><th class="text-end">Value</th><th>Step</th><th>Submitted by</th><th /></tr></thead>
        <tbody>
          <tr v-for="t in targets" :key="t.uuid"><td class="font-weight-medium">{{ t.name }} <span class="text-caption">v{{ t.version }}</span></td><td>{{ metrics[t.metric] }}</td><td class="text-caption">{{ t.start_date }} → {{ t.end_date }}</td>
            <td class="text-end">{{ fmt(t, t.target_value) }}</td><td>{{ t.step }} of {{ t.steps }}</td><td class="text-caption">{{ t.creator }}</td>
            <td class="text-end"><VBtn color="primary" size="small" @click="router.get(`/sales/targets/${t.uuid}`)">Review</VBtn></td></tr>
          <tr v-if="!targets.length"><td colspan="7" class="text-medium-emphasis text-center py-6">Nothing is waiting for you.</td></tr>
        </tbody>
      </VTable>
    </VCard>
  </VContainer>
</template>
