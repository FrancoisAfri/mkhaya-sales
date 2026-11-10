<script setup lang="ts">
// A dashboard opened from a share link (gap 8): read-only, no login, numbers as its creator sees them.
import { GridItem, GridLayout } from 'grid-layout-plus'
import { computed, reactive, ref } from 'vue'
import Visual from '@/Components/BI/Visual.vue'

defineOptions({ layout: null })
const props = defineProps<{ token: string; dashboard: any; company: string | null; filters: Record<string, any>; expires: string; sharedBy: string; lastSale: string | null }>()
const widgets = ref<any[]>((props.dashboard.widgets ?? []).map((w: any) => ({ ...w, i: w.id })))
const f = reactive<Record<string, any>>({ ...props.filters })
const applied = computed(() => Object.fromEntries(Object.entries(f).filter(([, v]) => v !== null && v !== undefined && v !== '')))
const endpoint = `/share/${props.token}/query`
</script>

<template>
  <div class="sh-page">
    <header class="sh-head">
      <div>
        <p class="sh-eyebrow">{{ company }} · shared dashboard</p>
        <h1 class="sh-title">{{ dashboard.name }} <VChip v-if="dashboard.certified" size="small" color="success" variant="tonal" prepend-icon="tabler-rosette-discount-check">Certified</VChip></h1>
        <p class="sh-sub">Data up to {{ lastSale ?? '—' }} · shared by {{ sharedBy }} · link valid until {{ expires }} · read-only</p>
      </div>
      <img src="/images/logo_default.png" alt="Mkhaya Sales" class="sh-logo">
    </header>
    <GridLayout v-model:layout="widgets" :col-num="12" :row-height="30" :margin="[16, 16]" :is-draggable="false" :is-resizable="false" :responsive="true"
                :breakpoints="{ lg: 1200, md: 996, sm: 768, xs: 480, xxs: 0 }" :cols="{ lg: 12, md: 12, sm: 6, xs: 2, xxs: 1 }" use-css-transforms>
      <GridItem v-for="w in widgets" :key="w.i" :i="w.i" :x="w.x" :y="w.y" :w="w.w" :h="w.h">
        <Visual :widget="w" :filters="applied" :endpoint="endpoint" shared />
      </GridItem>
    </GridLayout>
    <p class="sh-foot">Mkhaya Sales · figures from live sales records; forecasts are estimates.</p>
  </div>
</template>

<style scoped>
.sh-page { min-height: 100vh; background: #F7F7FA; padding: 28px 24px 40px; }
.sh-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin: 0 8px 16px; }
.sh-eyebrow { margin: 0 0 4px; font-size: 13px; font-weight: 600; color: #6A0DAD; }
.sh-title { margin: 0; font-size: 28px; font-weight: 700; letter-spacing: -.6px; color: #16161F; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.sh-sub { margin: 4px 0 0; font-size: 13px; color: #8A8A99; }
.sh-logo { height: 40px; }
.sh-foot { text-align: center; font-size: 12px; color: #A0A0B0; margin-top: 24px; }
</style>
