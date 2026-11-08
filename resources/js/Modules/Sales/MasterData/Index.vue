<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

type Field = { key: string; label: string; type: string; required?: boolean; options?: string }
const props = defineProps<{
  resource: string
  definition: { label: string; fields: Field[]; aliases?: boolean; filters?: string[] }
  resources: { key: string; label: string }[]
  rows: { data: any[]; total: number; current_page: number; last_page: number; per_page: number }
  options: Record<string, { value: number; title: string }[]>
  filters: Record<string, any>
  canManage: boolean
}>()

const base = computed(() => `/sales/master/${props.resource}`)
const search = ref(props.filters.q ?? '')
const needsReview = ref(!!props.filters.needs_review)
const optionTitle = (field: Field, id: any) => props.options[field.options!]?.find(o => o.value === id)?.title ?? '—'
const columns = computed(() => props.definition.fields.filter(f => f.type !== 'textarea').slice(0, 7))
const headers = computed(() => [
  ...columns.value.map(f => ({ title: f.label, key: f.key })),
  { title: '', key: 'actions', sortable: false, align: 'end' as const },
])

function reload(extra: Record<string, any> = {}) {
  router.get(base.value, { ...props.filters, q: search.value || undefined, needs_review: needsReview.value ? 1 : undefined, ...extra }, { preserveState: true, preserveScroll: true })
}

const dialog = ref(false)
const editing = ref<any>(null)
const form = useForm<Record<string, any>>({})
function open(row: any = null) {
  editing.value = row
  const data: Record<string, any> = {}
  props.definition.fields.forEach(f => { data[f.key] = row ? row[f.key] : (f.type === 'switch' ? true : (f.key === 'currency' ? 'ZAR' : null)) })
  Object.keys(form.data()).forEach(k => delete (form as any)[k])
  Object.assign(form, data)
  form.defaults(data)
  form.clearErrors()
  dialog.value = true
}
function save() {
  const opts = { preserveScroll: true, onSuccess: () => { dialog.value = false } }
  editing.value ? form.put(`${base.value}/${editing.value.id}`, opts) : form.post(base.value, opts)
}
function deactivate(row: any) {
  if (window.confirm('Deactivate this record? Its sales history is kept.')) router.delete(`${base.value}/${row.id}`, { preserveScroll: true })
}

const aliasRow = ref<any>(null)
const aliasForm = useForm({ alias_code: '', alias_name: '' })
function addAlias() {
  aliasForm.post(`${base.value}/${aliasRow.value.id}/aliases`, {
    preserveScroll: true,
    onSuccess: () => { aliasForm.reset(); aliasRow.value = props.rows.data.find(r => r.id === aliasRow.value.id) ?? null },
  })
}
function removeAlias(a: any) {
  router.delete(`${base.value}/${aliasRow.value.id}/aliases/${a.id}`, {
    preserveScroll: true,
    onSuccess: () => { aliasRow.value = props.rows.data.find(r => r.id === aliasRow.value.id) ?? null },
  })
}
</script>

<template>
  <VContainer fluid class="pa-4 pa-sm-6">
    <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
      <div>
        <h3 class="text-h5 font-weight-bold mb-1">{{ definition.label }}</h3>
        <p class="text-body-2 text-medium-emphasis mb-0">{{ rows.total }} records</p>
      </div>
      <div class="d-flex gap-2">
        <VBtn variant="tonal" prepend-icon="tabler-download" :href="`${base}/export`">Export CSV</VBtn>
        <VBtn v-if="canManage" color="primary" prepend-icon="tabler-plus" @click="open()">Add</VBtn>
      </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-4">
      <VChip v-for="r in resources" :key="r.key" :color="r.key === resource ? 'primary' : undefined"
             :variant="r.key === resource ? 'flat' : 'tonal'" @click="router.get(`/sales/master/${r.key}`)">{{ r.label }}</VChip>
    </div>

    <VCard elevation="0" border rounded="lg">
      <VCardText class="d-flex flex-wrap gap-3 align-center">
        <VTextField v-model="search" density="compact" variant="outlined" prepend-inner-icon="tabler-search"
                    placeholder="Search code or name" hide-details clearable style="max-width: 320px" @keyup.enter="reload({ page: 1 })" @click:clear="search = ''; reload({ page: 1 })" />
        <template v-for="f in definition.fields.filter(f => (definition.filters ?? []).includes(f.key) && f.type === 'select')" :key="f.key">
          <VSelect :model-value="filters[f.key] ? Number(filters[f.key]) : null" :items="options[f.options!] ?? []" :label="f.label"
                   density="compact" variant="outlined" hide-details clearable style="max-width: 220px"
                   @update:model-value="v => reload({ [f.key]: v ?? undefined, page: 1 })" />
        </template>
        <VCheckbox v-if="definition.aliases" v-model="needsReview" label="Needs review (created from a sales file)" hide-details density="compact" @update:model-value="reload({ page: 1 })" />
      </VCardText>
      <VDataTableServer :headers="headers" :items="rows.data" :items-length="rows.total" :items-per-page="rows.per_page"
                        :page="rows.current_page" density="comfortable"
                        @update:options="o => (o.page !== rows.current_page || o.itemsPerPage !== rows.per_page) && reload({ page: o.page, per_page: o.itemsPerPage })">
        <template v-for="f in columns" :key="f.key" #[`item.${f.key}`]="{ item }">
          <VChip v-if="f.type === 'switch'" size="small" :color="item[f.key] ? 'success' : 'default'" variant="tonal">{{ item[f.key] ? 'Active' : 'Inactive' }}</VChip>
          <span v-else-if="f.type === 'select'">{{ optionTitle(f, item[f.key]) }}</span>
          <span v-else>{{ item[f.key] ?? '—' }}</span>
          <VChip v-if="f.key === columns[1]?.key && item.needs_review" size="x-small" color="warning" class="ms-2">review</VChip>
        </template>
        <template #item.actions="{ item }">
          <VBtn v-if="definition.aliases" icon="tabler-tags" size="small" variant="text" title="Aliases" @click="aliasRow = item" />
          <VBtn v-if="canManage" icon="tabler-pencil" size="small" variant="text" @click="open(item)" />
          <VBtn v-if="canManage && item.is_active" icon="tabler-ban" size="small" variant="text" color="error" @click="deactivate(item)" />
        </template>
      </VDataTableServer>
    </VCard>

    <VDialog v-model="dialog" max-width="640">
      <VCard :title="(editing ? 'Edit ' : 'Add ') + definition.label.toLowerCase().replace(/s$/, '')">
        <VCardText>
          <VRow dense>
            <VCol v-for="f in definition.fields" :key="f.key" cols="12" :md="f.type === 'switch' ? 12 : 6">
              <VSwitch v-if="f.type === 'switch'" v-model="form[f.key]" :label="f.label" color="success" hide-details inset />
              <VSelect v-else-if="f.type === 'select'" v-model="form[f.key]" :items="options[f.options!] ?? []" :label="f.label"
                       :error-messages="form.errors[f.key]" clearable density="compact" variant="outlined" />
              <VTextField v-else v-model="form[f.key]" :label="f.label + (f.required ? ' *' : '')" :type="f.type === 'number' ? 'number' : 'text'"
                          :error-messages="form.errors[f.key]" density="compact" variant="outlined" />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="dialog = false">Cancel</VBtn>
          <VBtn color="primary" :loading="form.processing" @click="save">Save</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog :model-value="!!aliasRow" max-width="560" @update:model-value="v => !v && (aliasRow = null)">
      <VCard v-if="aliasRow" title="Aliases" :subtitle="'Other codes and names source files use for this record'">
        <VCardText>
          <VTable density="compact" class="mb-4">
            <thead><tr><th>Alias code</th><th>Alias name</th><th /></tr></thead>
            <tbody>
              <tr v-for="a in aliasRow.aliases ?? []" :key="a.id">
                <td>{{ a.alias_code ?? '—' }}</td><td>{{ a.alias_name ?? '—' }}</td>
                <td class="text-end"><VBtn v-if="canManage" icon="tabler-x" size="x-small" variant="text" @click="removeAlias(a)" /></td>
              </tr>
              <tr v-if="!(aliasRow.aliases ?? []).length"><td colspan="3" class="text-medium-emphasis">No aliases yet.</td></tr>
            </tbody>
          </VTable>
          <div v-if="canManage" class="d-flex gap-2">
            <VTextField v-model="aliasForm.alias_code" label="Alias code" density="compact" variant="outlined" :error-messages="aliasForm.errors.alias_code" />
            <VTextField v-model="aliasForm.alias_name" label="Alias name" density="compact" variant="outlined" />
            <VBtn color="primary" :loading="aliasForm.processing" @click="addAlias">Add</VBtn>
          </div>
        </VCardText>
      </VCard>
    </VDialog>
  </VContainer>
</template>
