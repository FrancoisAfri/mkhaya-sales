<script setup lang="ts">
import { router } from '@inertiajs/vue3'

import { reactive, ref } from 'vue'

const props = defineProps<{ month: string; totals: any; quota: any; byUser: any[]; byCategory: any[]; byModel: any[]; recent: any[]; usdToZar: number
  userDefault: number | null; people: any[]; canManage: boolean }>()
// Per-user allowances (2026-11-10): blank = no ceiling of their own.
const defaultTokens = ref<number | null>(props.userDefault)
const own = reactive<Record<number, number | null>>(Object.fromEntries(props.people.map(p => [p.id, p.own])))
const clean = (v: any) => (v === '' || v === null || v === undefined ? null : Number(v))
const saveDefault = () => router.put('/sales/ai-usage/allowance', { tokens: clean(defaultTokens.value) }, { preserveScroll: true })
const saveUser = (p: any) => router.put(`/sales/ai-usage/users/${p.id}/allowance`, { tokens: clean(own[p.id]) }, { preserveScroll: true })
const status = (p: any) => (p.blocked_reason === 'company' ? ['Company allowance used up', 'error'] : p.blocked_reason === 'user' ? ['Blocked — own allowance used up', 'error']
  : p.quota !== null && p.percent >= 80 ? ['Nearly used up', 'warning'] : ['Can use AI', 'success'])
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

    <VCard class="mb-5">
      <VCardText>
        <div class="d-flex flex-wrap justify-space-between align-end gap-3 mb-3">
          <div><strong>Allowance per person</strong>
            <p class="text-caption text-medium-emphasis mb-0">Each person may use up to their own monthly ceiling, within the company allowance. When it is used up they are told and AI stops for them until the 1st — no request is sent. Blank = no ceiling of their own; 0 = no AI.</p></div>
          <div v-if="canManage" class="d-flex align-center gap-2">
            <VTextField v-model.number="defaultTokens" type="number" min="0" label="Default for everyone (tokens / month)" placeholder="No ceiling" density="compact" variant="outlined" hide-details style="width: 280px" />
            <VBtn color="primary" @click="saveDefault">Save default</VBtn>
          </div>
        </div>
        <VTable density="comfortable" hover>
          <thead><tr><th>Person</th><th class="text-end">Used this month</th><th class="text-end">Ceiling</th><th style="width: 22%">Used</th><th>Status</th><th v-if="canManage" style="width: 260px">Own ceiling</th></tr></thead>
          <tbody>
            <tr v-for="p in people" :key="p.id">
              <td><strong>{{ p.name }}</strong><div class="text-caption text-medium-emphasis">{{ p.email }}</div></td>
              <td class="text-end">{{ num(p.used) }}</td>
              <td class="text-end">{{ p.quota === null ? 'No ceiling' : num(p.quota) }}<div v-if="p.own === null && p.quota !== null" class="text-caption text-medium-emphasis">company default</div></td>
              <td><VProgressLinear v-if="p.quota !== null" :model-value="Math.min(100, p.percent)" :color="p.percent >= 100 ? 'error' : p.percent >= 80 ? 'warning' : 'primary'" height="8" rounded /><span v-else class="text-caption text-medium-emphasis">—</span></td>
              <td><VChip size="small" variant="tonal" :color="status(p)[1]">{{ status(p)[0] }}</VChip></td>
              <td v-if="canManage"><div class="d-flex gap-2 align-center"><VTextField v-model.number="own[p.id]" type="number" min="0" placeholder="Default" density="compact" variant="outlined" hide-details />
                <VBtn size="small" variant="tonal" @click="saveUser(p)">Save</VBtn></div></td>
            </tr>
          </tbody>
        </VTable>
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
