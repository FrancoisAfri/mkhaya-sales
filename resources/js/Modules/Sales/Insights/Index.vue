<script setup lang="ts">
import axios from 'axios'
import { computed, reactive, ref } from 'vue'

const props = defineProps<{ anomalies: any; lifecycle: any; forecast: any; customers: any[]; lastTwelveMonths: number; canUseAi: boolean }>()
const money = (n: any) => 'R ' + Number(n ?? 0).toLocaleString('en-ZA', { maximumFractionDigits: 0 })
const kindColor: Record<string, string> = { drop: 'error', stopped: 'error', spike: 'success', new: 'info' }
const stageColor: Record<string, string> = { new: 'info', growing: 'success', mature: 'primary', declining: 'warning', dormant: 'error' }
const stageFilter = ref<string | null>(null)
const products = computed(() => (props.lifecycle?.products ?? []).filter((p: any) => !stageFilter.value || p.stage === stageFilter.value))

// What-if: next 12 months from the forecast, adjusted by the levers below.
const base = computed(() => (props.forecast?.months ?? []).slice(1, 13).reduce((a: number, m: any) => a + m.forecast, 0) || props.lastTwelveMonths)
const w = reactive({ volume: 0, price: 0, lost: [] as number[], newMonthly: 0 })
const lostValue = computed(() => props.customers.filter(c => w.lost.includes(c.customer_id)).reduce((a, c) => a + c.value, 0))
const lostShare = computed(() => props.lastTwelveMonths ? lostValue.value / props.lastTwelveMonths : 0)
const scenario = computed(() => base.value * (1 - lostShare.value) * (1 + w.volume / 100) * (1 + w.price / 100) + w.newMonthly * 12)

const ai = ref('')
const aiBusy = ref(false)
const aiError = ref('')
async function askAi() {
  aiBusy.value = true; aiError.value = ''
  try {
    const { data } = await axios.post('/ai-copilot/chat', { message: 'Give management five short, specific insights from our sales: what is growing, what is declining, which customers and products to investigate, and how the month is tracking against target. Quote figures and periods.' })
    ai.value = data.message?.content ?? ''
  } catch (e: any) { aiError.value = e.response?.data?.message ?? 'The AI assistant is not available right now.' } finally { aiBusy.value = false }
}
</script>

<template>
  <VContainer fluid class="pa-4 pa-sm-6">
    <h3 class="text-h5 font-weight-bold mb-1">Insights</h3>
    <p class="text-body-2 text-medium-emphasis mb-4">Unusual movements, product lifecycle and what-if scenarios from the sales you are allowed to see.</p>

    <VCard elevation="0" border rounded="lg" class="mb-4">
      <VCardItem><VCardTitle>AI insights</VCardTitle><VCardSubtitle>Written by AI from your aggregated figures — review before acting on it</VCardSubtitle>
        <template #append><VBtn v-if="canUseAi" color="primary" prepend-icon="tabler-sparkles" :loading="aiBusy" @click="askAi">Generate</VBtn></template></VCardItem>
      <VCardText v-if="ai || aiError">
        <VAlert v-if="aiError" type="error" variant="tonal">{{ aiError }}</VAlert>
        <VChip v-if="ai" size="x-small" color="warning" variant="tonal" class="mb-2">AI-generated · requires human review</VChip>
        <div v-if="ai" style="white-space: pre-wrap" class="text-body-2">{{ ai }}</div>
      </VCardText>
    </VCard>

    <VRow>
      <VCol cols="12" lg="7">
        <VCard elevation="0" border rounded="lg" title="Unusual movements" :subtitle="anomalies?.period ? `Last 4 weeks (${anomalies.period}) against the usual 4 weeks of ${anomalies.baseline}; ±${anomalies.threshold}% or more` : ''">
          <VTable density="compact" fixed-header height="460">
            <thead><tr><th>Type</th><th>Name</th><th class="text-end">Last 4 weeks</th><th class="text-end">Usual</th><th class="text-end">Change</th><th /></tr></thead>
            <tbody>
              <tr v-for="a in anomalies?.items ?? []" :key="a.type + a.id">
                <td class="text-caption">{{ a.type }}</td>
                <td><a :href="`/sales/records?${a.type}_id=${a.id}`">{{ a.name }}</a></td>
                <td class="text-end">{{ money(a.recent) }}</td><td class="text-end">{{ money(a.usual) }}</td>
                <td class="text-end" :class="(a.change ?? 1) >= 0 ? 'text-success' : 'text-error'">{{ a.change === null ? 'new' : (a.change > 0 ? '+' : '') + a.change + '%' }}</td>
                <td><VChip size="x-small" :color="kindColor[a.kind]" variant="tonal">{{ a.kind }}</VChip></td>
              </tr>
              <tr v-if="!(anomalies?.items ?? []).length"><td colspan="6" class="text-medium-emphasis">Nothing unusual.</td></tr>
            </tbody>
          </VTable>
        </VCard>
      </VCol>
      <VCol cols="12" lg="5">
        <VCard elevation="0" border rounded="lg" title="What-if" subtitle="Next 12 months against the forecast">
          <VCardText class="d-flex flex-column gap-3">
            <div><div class="text-caption">Volume change: {{ w.volume }}%</div><VSlider v-model="w.volume" :min="-50" :max="50" :step="1" hide-details color="primary" /></div>
            <div><div class="text-caption">Price change: {{ w.price }}%</div><VSlider v-model="w.price" :min="-30" :max="30" :step="0.5" hide-details color="primary" /></div>
            <VAutocomplete v-model="w.lost" :items="customers" item-title="customer" item-value="customer_id" label="Customers lost" multiple chips closable-chips density="compact" variant="outlined" hide-details />
            <VTextField v-model.number="w.newMonthly" type="number" prefix="R" label="New business per month" density="compact" variant="outlined" hide-details />
            <VTable density="compact"><tbody>
              <tr><td>Forecast, next 12 months</td><td class="text-end">{{ money(base) }}</td></tr>
              <tr v-if="w.lost.length"><td>Lost customers' share</td><td class="text-end text-error">{{ (lostShare * 100).toFixed(1) }}%</td></tr>
              <tr><td class="font-weight-bold">Scenario</td><td class="text-end font-weight-bold">{{ money(scenario) }}</td></tr>
              <tr><td>Difference</td><td class="text-end" :class="scenario >= base ? 'text-success' : 'text-error'">{{ money(scenario - base) }} ({{ base ? ((scenario / base - 1) * 100).toFixed(1) : 0 }}%)</td></tr>
            </tbody></VTable>
            <p class="text-caption text-medium-emphasis mb-0">An estimate for discussion: changes are applied evenly across the year.</p>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard elevation="0" border rounded="lg" class="mt-4" title="Product lifecycle" subtitle="From first and last sale, and the last 3 months against the same months last year">
      <VCardText class="d-flex gap-2 flex-wrap pb-0">
        <VChip :variant="!stageFilter ? 'flat' : 'tonal'" @click="stageFilter = null">All</VChip>
        <VChip v-for="(n, s) in lifecycle?.counts ?? {}" :key="s" :color="stageColor[s as string]" :variant="stageFilter === s ? 'flat' : 'tonal'" @click="stageFilter = s as string">{{ s }} · {{ n }}</VChip>
      </VCardText>
      <VTable density="compact">
        <thead><tr><th>Product</th><th>Stage</th><th>First sale</th><th>Last sale</th><th class="text-end">Last 3 months</th><th class="text-end">vs last year</th></tr></thead>
        <tbody><tr v-for="p in products" :key="p.product_id">
          <td>{{ p.product }}</td><td><VChip size="x-small" :color="stageColor[p.stage]" variant="tonal">{{ p.stage }}</VChip></td>
          <td class="text-caption">{{ p.first_sale }}</td><td class="text-caption">{{ p.last_sale }}</td>
          <td class="text-end">{{ money(p.last_3_months) }}</td><td class="text-end" :class="(p.growth ?? 0) >= 0 ? 'text-success' : 'text-error'">{{ p.growth === null ? '—' : p.growth + '%' }}</td>
        </tr></tbody>
      </VTable>
    </VCard>
  </VContainer>
</template>
