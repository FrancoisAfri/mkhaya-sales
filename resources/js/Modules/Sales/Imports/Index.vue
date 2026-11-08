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

    <div class="imp-steps mb-6">
      <section class="imp-step">
        <header><span>1</span><div><h4>What are you importing?</h4><p>Choose the kind of data in the file.</p></div></header>
        <div class="imp-types">
          <button v-for="t in targets" :key="t.key" :class="{ on: form.target === t.key }" @click="form.target = t.key; form.layout_id = null">{{ t.label }}</button>
        </div>
        <VRadioGroup v-if="form.target === 'sales'" v-model="form.source" hide-details class="mt-3">
          <VRadio value="upload" label="Daily or manual report (e.g. the email did not arrive)" />
          <VRadio value="historical" label="Historical sales migration" />
        </VRadioGroup>
      </section>

      <section class="imp-step">
        <header><span>2</span><div><h4>Which layout?</h4><p>{{ targetLayouts.length ? 'A saved layout reads the file exactly the same way every time.' : 'No saved layout yet — columns will be matched by their headings.' }}</p></div></header>
        <VSelect v-model="form.layout_id" :items="targetLayouts" item-title="name" item-value="id" clearable label="Saved layout" density="comfortable" variant="outlined" hide-details />
        <VSelect v-if="!['sales', 'targets'].includes(form.target)" v-model="form.mode" :items="Object.entries(modes).map(([v, t]) => ({ value: v, title: t }))"
                 label="When a code already exists" density="comfortable" variant="outlined" hide-details class="mt-3" />
        <div class="d-flex gap-2 mt-3">
          <VBtn variant="tonal" prepend-icon="tabler-download" :href="templateUrl">Download template</VBtn>
          <VBtn variant="text" prepend-icon="tabler-table-options" href="/sales/layouts">Build a layout</VBtn>
        </div>
      </section>

      <section class="imp-step">
        <header><span>3</span><div><h4>Upload and preview</h4><p>Nothing is saved until you have checked the preview and confirmed.</p></div></header>
        <VFileInput v-model="form.file" accept=".xlsx,.xls,.csv" label="XLSX, XLS or CSV — up to 40 MB" density="comfortable" variant="outlined"
                    prepend-icon="" prepend-inner-icon="tabler-paperclip" :error-messages="form.errors.file" />
        <VBtn color="primary" size="large" block :disabled="!form.file" :loading="form.processing" prepend-icon="tabler-player-play" class="mt-3" @click="submit">Validate and preview</VBtn>
      </section>
    </div>

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

<style scoped>
.imp-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; }
.imp-step { background: #fff; border: 1px solid #EEEEF3; border-radius: 18px; padding: 22px; }
.imp-step header { display: flex; gap: 14px; margin-bottom: 16px; }
.imp-step header span { flex: 0 0 32px; height: 32px; border-radius: 10px; background: #F3EEFA; color: #6A0DAD; font-weight: 800; display: flex; align-items: center; justify-content: center; }
.imp-step h4 { margin: 0; font-size: 15px; font-weight: 700; } .imp-step p { margin: 2px 0 0; font-size: 12.5px; color: #77778A; }
.imp-types { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.imp-types button { border: 1px solid #E6E6EE; background: #fff; border-radius: 12px; padding: 12px; font-weight: 600; font-size: 13px; color: #45455A; cursor: pointer; transition: all .15s; }
.imp-types button.on { border-color: #6A0DAD; background: #F8F4FD; color: #6A0DAD; box-shadow: 0 0 0 3px rgba(106,13,173,.08); }
</style>
