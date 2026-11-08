<script setup lang="ts">
import { router } from '@inertiajs/vue3'

const props = defineProps<{ month: string; totals: any; quota: any; byUser: any[]; byCategory: any[]; byModel: any[]; recent: any[]; usdToZar: number }>()
const num = (n: any) => Number(n ?? 0).toLocaleString('en-ZA')
const rand = (usd: any) => 'R ' + (Number(usd ?? 0) * props.usdToZar).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const rate = (r: any) => r.requests ? Math.round(r.ok / r.requests * 100) + '%' : '—'
</script>

<template>
  <VContainer fluid class="pa-4 pa-sm-6">
    <div class="d-flex flex-wrap justify-space-between align-end gap-3 mb-5">
      <div><h3 class="text-h5 mb-1">AI usage</h3><p class="text-body-2 text-medium-emphasis mb-0">Every AI request in your organisation: who asked, what about, which provider and model, tokens, estimated cost and outcome.</p></div>
      <VTextField :model-value="month" type="month" label="Month" density="compact" variant="outlined" hide-details style="max-width: 180px" @update:model-value="v => router.get('/sales/ai-usage', { month: v })" />
    </div>

    <VRow class="mb-2">
      <VCol v-for="k in [['Requests', num(totals.requests)], ['Tokens', num(totals.tokens)], ['Estimated cost', rand(totals.cost)], ['Successful', rate(totals)], ['People using AI', num(totals.users)]]" :key="k[0]" cols="6" md="">
        <VCard><VCardText><div class="text-caption text-medium-emphasis">{{ k[0] }}</div><div class="text-h5">{{ k[1] }}</div></VCardText></VCard>
      </VCol>
    </VRow>

    <VCard v-if="quota" class="mb-5">
      <VCardText>
        <div class="d-flex justify-space-between mb-2"><strong>Monthly token limit</strong><span class="text-medium-emphasis">{{ quota.unlimited ? 'Unlimited' : `${num(quota.used)} of ${num(quota.quota)} tokens (${quota.percent}%)` }}</span></div>
        <VProgressLinear v-if="!quota.unlimited" :model-value="quota.percent" :color="quota.exceeded ? 'error' : quota.low ? 'warning' : 'primary'" height="10" rounded />
        <p class="text-caption text-medium-emphasis mt-2 mb-0">When the limit is reached the assistant pauses until next month or a top-up — set by your Mkhaya administrator.</p>
      </VCardText>
    </VCard>

    <VRow>
      <VCol v-for="[title, rows] in [['By person', byUser], ['By request category', byCategory], ['By provider and model', byModel]]" :key="String(title)" cols="12" md="4">
        <VCard :title="String(title)">
          <VTable density="compact"><thead><tr><th>Name</th><th class="text-end">Requests</th><th class="text-end">Tokens</th><th class="text-end">Cost</th><th class="text-end">OK</th></tr></thead>
            <tbody><tr v-for="r in (rows as any[])" :key="r.name"><td class="text-truncate" style="max-width: 160px">{{ r.name }}</td><td class="text-end">{{ num(r.requests) }}</td><td class="text-end">{{ num(r.tokens) }}</td><td class="text-end">{{ rand(r.cost) }}</td><td class="text-end">{{ rate(r) }}</td></tr>
              <tr v-if="!(rows as any[]).length"><td colspan="5" class="text-medium-emphasis">No AI use this month.</td></tr></tbody></VTable>
        </VCard>
      </VCol>
    </VRow>

    <VCard title="Recent requests" class="mt-5">
      <VTable density="compact">
        <thead><tr><th>When</th><th>Who</th><th>Category</th><th>Provider · model</th><th class="text-end">Tokens</th><th class="text-end">Cost</th><th>Outcome</th><th>Asked</th></tr></thead>
        <tbody><tr v-for="(r, i) in recent" :key="i">
          <td class="text-no-wrap text-caption">{{ new Date(r.created_at).toLocaleString('en-ZA', { dateStyle: 'medium', timeStyle: 'short' }) }}</td><td class="text-caption">{{ r.email ?? 'system' }}</td>
          <td><VChip size="x-small" variant="tonal">{{ r.category ?? '—' }}</VChip></td><td class="text-caption">{{ r.provider ?? '—' }} · {{ r.model_used }}</td>
          <td class="text-end">{{ num(r.total_tokens) }}</td><td class="text-end">{{ rand(r.cost_usd) }}</td>
          <td><VChip size="x-small" :color="r.success ? 'success' : 'error'" variant="tonal">{{ r.success ? 'ok' : 'failed' }}</VChip></td>
          <td class="text-caption text-truncate" style="max-width: 280px" :title="r.error_message ?? r.input_summary">{{ r.input_summary }}</td>
        </tr></tbody>
      </VTable>
    </VCard>
  </VContainer>
</template>
