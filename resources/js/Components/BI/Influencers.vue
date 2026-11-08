<script setup lang="ts">
// Key influencers (BI spec §F): what explains the change on last year, across every dimension.
import { computed, ref } from 'vue'
import { fmt, GOOD, BAD } from './visuals'

const props = defineProps<{ data: any }>()
const emit = defineEmits<{ filter: [{ dimension: string; key: any; label: string }] }>()
const tab = ref<'decline' | 'growth'>(props.data?.overall?.change < 0 ? 'decline' : 'growth')
const rows = computed(() => props.data?.[tab.value] ?? [])
const maxChange = computed(() => Math.max(1, ...rows.value.map((r: any) => Math.abs(r.change))))
const o = computed(() => props.data?.overall ?? {})
function pick(r: any) { emit('filter', { dimension: r.dimension, key: r.key, label: r.name }) }
</script>

<template>
  <div class="ki">
    <div class="ki__head">
      <div>
        <div class="ki__total" :style="{ color: (o.change ?? 0) >= 0 ? GOOD : BAD }">{{ (o.change ?? 0) >= 0 ? '+' : '−' }}{{ fmt(Math.abs(o.change ?? 0)) }}</div>
        <div class="ki__sub">vs last year ({{ o.growth === null ? 'no comparison' : (o.growth > 0 ? '+' : '') + o.growth + '%' }})</div>
      </div>
      <div class="ki__seg">
        <button :class="{ on: tab === 'decline' }" @click="tab = 'decline'">What pulled sales down</button>
        <button :class="{ on: tab === 'growth' }" @click="tab = 'growth'">What lifted sales</button>
      </div>
    </div>
    <div class="ki__list">
      <button v-for="r in rows" :key="r.dimension + r.key" class="ki__row" @click="pick(r)">
        <div class="ki__label"><small>{{ r.dimension_label }}</small><strong>{{ r.name }}</strong></div>
        <div class="ki__bar"><span :style="{ width: Math.abs(r.change) / maxChange * 100 + '%', background: r.change < 0 ? BAD : GOOD }" /></div>
        <div class="ki__num">
          <strong :style="{ color: r.change < 0 ? BAD : GOOD }">{{ r.change < 0 ? '−' : '+' }}{{ fmt(Math.abs(r.change)) }}</strong>
          <small>{{ r.growth === null ? 'new' : (r.growth > 0 ? '+' : '') + r.growth + '%' }} · {{ r.share_of_change }}% of {{ r.change < 0 ? 'falls' : 'gains' }} in {{ r.dimension_label.toLowerCase() }}</small>
        </div>
      </button>
      <p v-if="!rows.length" class="ki__none">Nothing {{ tab === 'decline' ? 'fell' : 'grew' }} against last year for this selection.</p>
    </div>
    <div v-if="data.factors?.length" class="ki__factors">
      <small>Where the change is concentrated:</small>
      <span v-for="f in data.factors.slice(0, 4)" :key="f.dimension">{{ f.label }} <b>{{ f.score }}%</b></span>
    </div>
  </div>
</template>

<style scoped>
.ki { display: flex; flex-direction: column; height: 100%; gap: 10px; }
.ki__head { display: flex; justify-content: space-between; align-items: flex-end; gap: 10px; flex-wrap: wrap; }
.ki__total { font-family: Montserrat, sans-serif; font-size: 24px; font-weight: 700; letter-spacing: -.5px; }
.ki__sub { font-size: 12px; color: #77778A; }
.ki__seg { display: inline-flex; background: #F3F1F8; border-radius: 10px; padding: 3px; }
.ki__seg button { border: 0; background: transparent; padding: 5px 10px; font-size: 12px; font-weight: 600; color: #5B5B6E; border-radius: 8px; cursor: pointer; }
.ki__seg button.on { background: #fff; color: #6A0DAD; box-shadow: 0 1px 2px rgba(17,17,26,.08); }
.ki__list { flex: 1; min-height: 0; overflow: auto; display: flex; flex-direction: column; gap: 2px; }
.ki__row { display: grid; grid-template-columns: minmax(120px, 1.2fr) 1fr minmax(150px, 1.1fr); gap: 12px; align-items: center; padding: 8px 6px; border: 0; background: transparent; border-radius: 10px; text-align: left; cursor: pointer; }
.ki__row:hover { background: #FAF8FD; }
.ki__label small, .ki__num small { display: block; font-size: 11px; color: #8A8A99; }
.ki__label strong { font-size: 13px; color: #16161F; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.ki__bar { height: 8px; background: #F3F1F8; border-radius: 99px; overflow: hidden; }
.ki__bar span { display: block; height: 100%; border-radius: 99px; }
.ki__num { text-align: right; } .ki__num strong { font-size: 13px; }
.ki__none { color: #8A8A99; font-size: 13px; padding: 12px 6px; }
.ki__factors { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; font-size: 12px; color: #5B5B6E; border-top: 1px solid #F1F1F5; padding-top: 8px; }
.ki__factors span { background: #F8F6FC; border-radius: 8px; padding: 2px 8px; }
</style>
