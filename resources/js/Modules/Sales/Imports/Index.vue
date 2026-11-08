<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps<{
  targets: { key: string; label: string }[]
  layouts: { id: number; uuid: string; name: string; target: string; version: number; ingestion_mode: string }[]
  batches: { data: any[]; total: number; current_page: number; per_page: number }
  filters: Record<string, any>
  modes: Record<string, string>
}>()

const step = ref(1)
const form = useForm({ target: props.targets[0]?.key ?? 'sales', layout_id: null as number | null, mode: 'upsert', source: 'upload', file: null as File | null })
const targetLayouts = computed(() => props.layouts.filter(l => l.target === form.target))
const chosenLayout = computed(() => props.layouts.find(l => l.id === form.layout_id))
const templateUrl = computed(() => `/sales/imports/template/${form.target}${chosenLayout.value ? `?layout=${chosenLayout.value.uuid}` : ''}`)

function submit() { form.post('/sales/imports', { forceFormData: true }) }

const statusColor: Record<string, string> = { committed: 'success', staged: 'info', failed: 'error', cancelled: 'default', staging: 'warning', committing: 'warning', uploaded: 'default' }
const headers = [
  { title: 'File', key: 'file_name' }, { title: 'Type', key: 'target' }, { title: 'Source', key: 'source' },
  { title: 'Layout', key: 'layout' }, { title: 'Rows', key: 'total_rows' }, { title: 'Added', key: 'inserted_rows' },
  { title: 'Errors', key: 'invalid_rows' }, { title: 'Status', key: 'status' }, { title: 'When', key: 'created_at' },
]
const when = (s: string) => new Date(s).toLocaleString('en-ZA', { dateStyle: 'medium', timeStyle: 'short' })
</script>

<template>
  <VContainer fluid class="pa-4 pa-sm-6">
    <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
      <div>
        <h3 class="text-h5 font-weight-bold mb-1">Data Import Centre</h3>
        <p class="text-body-2 text-medium-emphasis mb-0">Nothing is saved until you have previewed the file and confirmed it.</p>
      </div>
      <VBtn variant="tonal" prepend-icon="tabler-table-options" href="/sales/layouts">File layouts</VBtn>
    </div>

    <VCard elevation="0" border rounded="lg" class="mb-6">
      <VCardText>
        <VStepperVertical v-model="step" flat hide-actions>
          <VStepperVerticalItem :value="1" title="What are you importing?" :complete="step > 1">
            <VBtnToggle v-model="form.target" mandatory color="primary" variant="outlined" divided class="mb-4">
              <VBtn v-for="t in targets" :key="t.key" :value="t.key" @click="form.layout_id = null">{{ t.label }}</VBtn>
            </VBtnToggle>
            <div v-if="form.target === 'sales'" class="mb-2">
              <VRadioGroup v-model="form.source" inline hide-details>
                <VRadio value="upload" label="Daily / manual report (e.g. the email did not arrive)" />
                <VRadio value="historical" label="Historical sales migration" />
              </VRadioGroup>
            </div>
            <VBtn color="primary" class="mt-2" @click="step = 2">Next</VBtn>
          </VStepperVerticalItem>

          <VStepperVerticalItem :value="2" title="Which file layout?" :complete="step > 2">
            <VSelect v-model="form.layout_id" :items="targetLayouts" item-title="name" item-value="id" clearable
                     label="Saved layout (recommended for a recurring report)" density="compact" variant="outlined" style="max-width: 460px"
                     :hint="targetLayouts.length ? 'Without a layout, columns are matched by their headings.' : 'No layouts yet — create one under File layouts, or let the system match headings.'" persistent-hint />
            <VSelect v-if="form.target !== 'sales'" v-model="form.mode" :items="Object.entries(modes).map(([v, t]) => ({ value: v, title: t }))"
                     label="When a code already exists" density="compact" variant="outlined" class="mt-4" style="max-width: 460px" />
            <div class="d-flex gap-2 mt-4">
              <VBtn variant="text" @click="step = 1">Back</VBtn>
              <VBtn variant="tonal" prepend-icon="tabler-download" :href="templateUrl">Download template</VBtn>
              <VBtn color="primary" @click="step = 3">Next</VBtn>
            </div>
          </VStepperVerticalItem>

          <VStepperVerticalItem :value="3" title="Upload the completed file">
            <VFileInput v-model="form.file" accept=".xlsx,.xls,.csv" label="XLSX, XLS or CSV (max 40 MB)" density="compact" variant="outlined"
                        prepend-icon="" prepend-inner-icon="tabler-paperclip" :error-messages="form.errors.file" style="max-width: 460px" />
            <div class="d-flex gap-2 mt-2">
              <VBtn variant="text" @click="step = 2">Back</VBtn>
              <VBtn color="primary" :disabled="!form.file" :loading="form.processing" prepend-icon="tabler-player-play" @click="submit">Validate and preview</VBtn>
            </div>
          </VStepperVerticalItem>
        </VStepperVertical>
      </VCardText>
    </VCard>

    <VCard elevation="0" border rounded="lg" title="Import history">
      <VCardText class="d-flex gap-3 flex-wrap">
        <VSelect :model-value="filters.target ?? null" :items="targets" item-title="label" item-value="key" label="Type" clearable density="compact" variant="outlined" hide-details style="max-width: 220px"
                 @update:model-value="v => router.get('/sales/imports', { ...filters, target: v ?? undefined }, { preserveState: true })" />
        <VSelect :model-value="filters.status ?? null" :items="['staged', 'committed', 'failed', 'cancelled']" label="Status" clearable density="compact" variant="outlined" hide-details style="max-width: 200px"
                 @update:model-value="v => router.get('/sales/imports', { ...filters, status: v ?? undefined }, { preserveState: true })" />
      </VCardText>
      <VDataTableServer :headers="headers" :items="batches.data" :items-length="batches.total" :items-per-page="batches.per_page" :page="batches.current_page" density="comfortable"
                        @update:options="o => o.page !== batches.current_page && router.get('/sales/imports', { ...filters, page: o.page }, { preserveState: true })"
                        @click:row="(_: any, { item }: any) => router.get(`/sales/imports/${item.uuid}`)" hover>
        <template #item.layout="{ item }">{{ item.layout?.name ?? '—' }}</template>
        <template #item.status="{ item }"><VChip size="small" :color="statusColor[item.status]" variant="tonal">{{ item.status }}</VChip></template>
        <template #item.created_at="{ item }">{{ when(item.created_at) }}</template>
      </VDataTableServer>
    </VCard>
  </VContainer>
</template>
