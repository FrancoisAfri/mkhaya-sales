<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'

const props = defineProps<{ record: any; fields: string[]; canAdjust: boolean; canApprove: boolean }>()
const r = props.record
const money = (n: any) => 'R ' + Number(n ?? 0).toLocaleString('en-ZA', { minimumFractionDigits: 2 })
const form = useForm({ field: 'net_value', new_value: '', reason: '' })
const color: Record<string, string> = { pending: 'warning', applied: 'success', rejected: 'error' }
</script>

<template>
  <VContainer fluid class="pa-4 pa-sm-6">
    <VBtn variant="text" size="small" prepend-icon="tabler-arrow-left" href="/sales/records" class="mb-2">Sales records</VBtn>
    <VRow>
      <VCol cols="12" md="6">
        <VCard elevation="0" border rounded="lg" :title="`Sale #${r.id}`" :subtitle="r.transaction_date?.slice(0, 10)">
          <VTable density="compact">
            <tbody>
              <tr><td>Customer</td><td>{{ r.customer?.customer_name }} ({{ r.customer?.customer_code }})</td></tr>
              <tr><td>Product</td><td>{{ r.product?.product_name }} ({{ r.product?.product_code }})</td></tr>
              <tr><td>Quantity</td><td>{{ r.quantity }} {{ r.unit_of_measure ?? '' }}</td></tr>
              <tr><td>Unit price</td><td>{{ r.unit_price ?? '—' }}</td></tr>
              <tr><td>Gross / discount / net</td><td>{{ money(r.gross_value) }} / {{ money(r.discount) }} / <strong>{{ money(r.net_value) }}</strong></td></tr>
              <tr><td>Source reference</td><td>{{ r.external_id ?? '—' }}</td></tr>
              <tr><td>Imported from</td><td><a v-if="r.batch" :href="`/sales/imports/${r.batch.uuid}`">{{ r.batch.file_name }}</a> (row {{ r.source_row }})</td></tr>
              <tr v-for="(v, k) in r.attributes ?? {}" :key="k"><td class="text-medium-emphasis">{{ k }}</td><td>{{ v }}</td></tr>
            </tbody>
          </VTable>
        </VCard>
      </VCol>
      <VCol cols="12" md="6">
        <VCard v-if="canAdjust" elevation="0" border rounded="lg" title="Request a correction" subtitle="The imported value is kept; another person approves the change" class="mb-4">
          <VCardText class="d-flex flex-column gap-3">
            <VSelect v-model="form.field" :items="fields" label="Field" density="compact" variant="outlined" hide-details />
            <VTextField v-model="form.new_value" label="Correct value" density="compact" variant="outlined" :error-messages="form.errors.new_value" />
            <VTextarea v-model="form.reason" label="Reason" rows="2" density="compact" variant="outlined" :error-messages="form.errors.reason" />
            <VBtn color="primary" :loading="form.processing" @click="form.post(`/sales/records/${r.id}/adjust`, { preserveScroll: true, onSuccess: () => form.reset() })">Submit</VBtn>
          </VCardText>
        </VCard>
        <VCard elevation="0" border rounded="lg" title="Correction history">
          <VTable density="compact">
            <thead><tr><th>Field</th><th>Imported</th><th>New</th><th>Reason</th><th>Status</th><th /></tr></thead>
            <tbody>
              <tr v-for="a in r.adjustments" :key="a.id">
                <td>{{ a.field }}</td><td>{{ a.original_value }}</td><td>{{ a.new_value }}</td>
                <td class="text-caption">{{ a.reason }}<br><span class="text-medium-emphasis">{{ a.requester?.email }}</span></td>
                <td><VChip size="x-small" :color="color[a.status]" variant="tonal">{{ a.status }}</VChip></td>
                <td class="text-no-wrap">
                  <template v-if="canApprove && a.status === 'pending'">
                    <VBtn size="x-small" color="success" variant="tonal" @click="router.post(`/sales/adjustments/${a.id}/decide`, { decision: 'apply' }, { preserveScroll: true })">Apply</VBtn>
                    <VBtn size="x-small" color="error" variant="text" @click="router.post(`/sales/adjustments/${a.id}/decide`, { decision: 'reject' }, { preserveScroll: true })">Reject</VBtn>
                  </template>
                </td>
              </tr>
              <tr v-if="!r.adjustments.length"><td colspan="6" class="text-medium-emphasis">No corrections.</td></tr>
            </tbody>
          </VTable>
        </VCard>
      </VCol>
    </VRow>
  </VContainer>
</template>
