<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import { computed, ref } from 'vue'

type FieldDef = { key: string; label: string; type: string; required: boolean }
const props = defineProps<{
  layouts: any[]
  targets: Record<string, string>
  modes: Record<string, string>
  fields: Record<string, FieldDef[]>
}>()

const dialog = ref(false)
const editing = ref<any>(null)
const sample = ref<File | null>(null)
const sampleInfo = ref<{ sheets: string[]; headings: string[]; sample: any[] } | null>(null)
const sampleError = ref('')
const reading = ref(false)
const blank = () => ({
  name: '', target: 'sales', sheet: '', header_row: 1, date_format: '', decimal_comma: false,
  ingestion_mode: 'append', unknown_masters: 'create', keep_unmapped: true, is_active: true,
  mapping: [] as { source: string; field: string | null }[],
})
const form = useForm(blank())

const fieldItems = computed(() => [
  { value: null, title: '— ignore this column —' },
  ...(props.fields[form.target] ?? []).map(f => ({ value: f.key, title: f.label + (f.required ? ' *' : '') })),
])
const requiredMissing = computed(() => (props.fields[form.target] ?? []).filter(f => f.required && !form.mapping.some(m => m.field === f.key)).map(f => f.label))

function open(layout: any = null) {
  editing.value = layout
  sampleInfo.value = null
  sample.value = null
  sampleError.value = ''
  const data = layout ? { ...blank(), ...layout, sheet: layout.sheet ?? '', date_format: layout.date_format ?? '', mapping: layout.mapping.map((m: any) => ({ ...m })) } : blank()
  Object.assign(form, data)
  form.clearErrors()
  dialog.value = true
}

async function readSample() {
  if (!sample.value) return
  reading.value = true
  sampleError.value = ''
  const fd = new FormData()
  fd.append('file', sample.value)
  fd.append('target', form.target)
  if (form.sheet) fd.append('sheet', form.sheet)
  fd.append('header_row', String(form.header_row || 1))
  try {
    const { data } = await axios.post('/sales/layouts/inspect', fd)
    sampleInfo.value = data
    // Keep choices already made for headings that are still there; guess the rest.
    const prior = Object.fromEntries(form.mapping.map(m => [m.source.toLowerCase(), m.field]))
    form.mapping = data.mapping.map((m: any) => ({ source: m.source, field: prior[m.source.toLowerCase()] ?? m.field }))
    if (!form.name) form.name = sample.value.name.replace(/\.[^.]+$/, '')
  } catch (e: any) {
    sampleError.value = e.response?.data?.message ?? 'The sample could not be read.'
  } finally {
    reading.value = false
  }
}

function customName(i: number) {
  const m = form.mapping[i]
  m.field = 'attr:' + m.source.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '')
}
function save() {
  const opts = { preserveScroll: true, onSuccess: () => { dialog.value = false } }
  editing.value ? form.put(`/sales/layouts/${editing.value.uuid}`, opts) : form.post('/sales/layouts', opts)
}
function retire(l: any) { if (window.confirm(`Retire layout "${l.name}"?`)) router.delete(`/sales/layouts/${l.uuid}`) }
</script>

<template>
  <VContainer fluid class="pa-4 pa-sm-6">
    <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
      <div>
        <h3 class="text-h5 font-weight-bold mb-1">File layouts</h3>
        <p class="text-body-2 text-medium-emphasis mb-0">Describe each report format once — any number of columns, any headings. New formats need no system change.</p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" @click="open()">New layout</VBtn>
    </div>

    <VCard elevation="0" border rounded="lg">
      <VTable>
        <thead><tr><th>Name</th><th>Imports</th><th>Columns</th><th>How rows are loaded</th><th>Version</th><th>Status</th><th /></tr></thead>
        <tbody>
          <tr v-for="l in layouts" :key="l.id">
            <td class="font-weight-medium">{{ l.name }}</td>
            <td>{{ targets[l.target] }}</td>
            <td>{{ l.mapping.length }} ({{ l.mapping.filter((m: any) => m.field?.startsWith('attr:')).length }} custom)</td>
            <td class="text-caption">{{ l.target === 'sales' ? modes[l.ingestion_mode] : 'Master data' }}</td>
            <td>v{{ l.version }}</td>
            <td><VChip size="small" :color="l.is_active ? 'success' : 'default'" variant="tonal">{{ l.is_active ? 'Active' : 'Retired' }}</VChip></td>
            <td class="text-end text-no-wrap">
              <VBtn icon="tabler-download" size="small" variant="text" :href="`/sales/imports/template/${l.target}?layout=${l.uuid}`" title="Template" />
              <VBtn icon="tabler-pencil" size="small" variant="text" @click="open(l)" />
              <VBtn v-if="l.is_active" icon="tabler-archive" size="small" variant="text" color="error" @click="retire(l)" />
            </td>
          </tr>
          <tr v-if="!layouts.length"><td colspan="7" class="text-medium-emphasis text-center py-6">No layouts yet. Create one from a sample of the report.</td></tr>
        </tbody>
      </VTable>
    </VCard>

    <VDialog v-model="dialog" max-width="980" scrollable>
      <VCard :title="editing ? `Edit layout — ${editing.name}` : 'New layout'">
        <VCardText>
          <VRow dense>
            <VCol cols="12" md="5"><VTextField v-model="form.name" label="Layout name *" density="compact" variant="outlined" :error-messages="form.errors.name" /></VCol>
            <VCol cols="12" md="3"><VSelect v-model="form.target" :items="Object.entries(targets).map(([v, t]) => ({ value: v, title: t }))" label="Imports" density="compact" variant="outlined" :disabled="!!editing" /></VCol>
            <VCol cols="6" md="2"><VTextField v-model="form.sheet" label="Sheet name" placeholder="first sheet" density="compact" variant="outlined" /></VCol>
            <VCol cols="6" md="2"><VTextField v-model.number="form.header_row" label="Header row" type="number" min="1" density="compact" variant="outlined" /></VCol>
            <VCol v-if="form.target === 'sales'" cols="12" md="6">
              <VSelect v-model="form.ingestion_mode" :items="Object.entries(modes).map(([v, t]) => ({ value: v, title: t }))" label="What does each file contain?" density="compact" variant="outlined" />
            </VCol>
            <VCol v-if="form.target === 'sales'" cols="12" md="6">
              <VSelect v-model="form.unknown_masters" :items="[{ value: 'create', title: 'Create unknown customers/products and flag them for review' }, { value: 'reject', title: 'Reject rows with unknown customers/products' }]" label="Unknown customer or product" density="compact" variant="outlined" />
            </VCol>
            <VCol cols="6" md="3"><VTextField v-model="form.date_format" label="Date format" placeholder="auto (e.g. d/m/Y)" density="compact" variant="outlined" /></VCol>
            <VCol cols="6" md="3"><VSwitch v-model="form.decimal_comma" label="Decimal comma" density="compact" hide-details inset /></VCol>
            <VCol cols="12" md="6"><VSwitch v-model="form.keep_unmapped" label="Keep columns not mapped below as custom fields" density="compact" hide-details inset /></VCol>
          </VRow>

          <VDivider class="my-4" />
          <div class="d-flex flex-wrap gap-3 align-center mb-3">
            <VFileInput v-model="sample" accept=".xlsx,.xls,.csv" label="Sample of the report" density="compact" variant="outlined" hide-details prepend-icon="" prepend-inner-icon="tabler-file-spreadsheet" style="max-width: 380px" />
            <VBtn variant="tonal" :loading="reading" :disabled="!sample" prepend-icon="tabler-scan" @click="readSample">Read columns</VBtn>
            <span v-if="sampleInfo" class="text-caption text-medium-emphasis">Sheets: {{ sampleInfo.sheets.join(', ') }}</span>
          </div>
          <VAlert v-if="sampleError" type="error" variant="tonal" class="mb-3">{{ sampleError }}</VAlert>
          <VAlert v-if="form.errors.mapping" type="error" variant="tonal" class="mb-3">{{ form.errors.mapping }}</VAlert>
          <VAlert v-else-if="form.mapping.length && requiredMissing.length" type="warning" variant="tonal" class="mb-3">Still to map: {{ requiredMissing.join(', ') }}</VAlert>

          <VTable v-if="form.mapping.length" density="compact">
            <thead><tr><th>Column in the file</th><th>Example</th><th style="width: 46%">Means</th><th /></tr></thead>
            <tbody>
              <tr v-for="(m, i) in form.mapping" :key="i">
                <td class="font-weight-medium">{{ m.source }}</td>
                <td class="text-caption text-medium-emphasis">{{ sampleInfo?.sample?.[0]?.[m.source] ?? '' }}</td>
                <td>
                  <VChip v-if="m.field?.startsWith('attr:')" size="small" color="info" variant="tonal" closable @click:close="m.field = null">Custom field: {{ m.field.slice(5) }}</VChip>
                  <VSelect v-else v-model="m.field" :items="fieldItems" density="compact" variant="outlined" hide-details :error-messages="(form.errors as any)[`mapping.${i}.field`]" />
                </td>
                <td class="text-end text-no-wrap">
                  <VBtn v-if="!m.field?.startsWith('attr:')" size="x-small" variant="text" @click="customName(i)">Keep as custom</VBtn>
                  <VBtn icon="tabler-x" size="x-small" variant="text" @click="form.mapping.splice(i, 1)" />
                </td>
              </tr>
            </tbody>
          </VTable>
          <p v-else class="text-medium-emphasis text-center py-6">Upload a sample of the report to list its columns.</p>
        </VCardText>
        <VCardActions>
          <span v-if="editing" class="text-caption text-medium-emphasis ms-4">Changing the mapping creates version {{ editing.version + 1 }}.</span>
          <VSpacer />
          <VBtn variant="text" @click="dialog = false">Cancel</VBtn>
          <VBtn color="primary" :loading="form.processing" :disabled="!form.mapping.length" @click="save">Save layout</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VContainer>
</template>
