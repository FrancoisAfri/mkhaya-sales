<script setup lang="ts">
import axios from 'axios'
import { ref, watch } from 'vue'

/**
 * Search-as-you-type picker for lists too large to load whole (customers, products).
 * `initial` carries the labels of values already chosen.
 */
const props = defineProps<{ resource: 'customers' | 'products'; modelValue: any; initial?: { value: number; title: string }[]; label?: string; multiple?: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [any] }>()
const items = ref<{ value: number; title: string }[]>([...(props.initial ?? [])])
const loading = ref(false)
let timer: ReturnType<typeof setTimeout> | undefined

function search(q: string | null) {
  clearTimeout(timer)
  timer = setTimeout(async () => {
    loading.value = true
    try {
      const { data } = await axios.get(`/sales/lookup/${props.resource}`, { params: { q: q ?? '' } })
      const chosen = items.value.filter(i => ([] as any[]).concat(props.modelValue ?? []).includes(i.value))
      items.value = [...chosen, ...data.filter((d: any) => !chosen.some(c => c.value === d.value))]
    } finally { loading.value = false }
  }, 250)
}
watch(() => props.initial, v => { if (v?.length) items.value = [...v, ...items.value.filter(i => !v.some(x => x.value === i.value))] })
</script>

<template>
  <VAutocomplete :model-value="modelValue" :items="items" :loading="loading" :label="label" :multiple="multiple" :chips="multiple" :closable-chips="multiple"
                 no-filter clearable density="compact" variant="outlined" hide-details placeholder="Type to search…"
                 @update:search="search" @focus="!items.length && search('')" @update:model-value="v => emit('update:modelValue', v)" />
</template>
