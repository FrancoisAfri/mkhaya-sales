<script setup lang="ts">
// Customer maps (BI spec §2 Maps): points per customer, bubbles per province, and a regional
// performance view coloured by growth. Tiles come from OpenStreetMap; every figure is server-side.
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { fmt, GOOD, AMBER, BAD, MUTED } from './visuals'

const props = defineProps<{ data: any; type: string }>()
const emit = defineEmits<{ filter: [{ dimension: string; key: any; label: string }] }>()
const el = ref<HTMLElement | null>(null)
let map: L.Map | null = null
let layer: L.LayerGroup | null = null

const growthColour = (g: number | null) => (g === null ? MUTED : g >= 2 ? GOOD : g >= -2 ? AMBER : BAD)

function draw() {
  if (!map || !props.data) return
  layer?.remove()
  layer = L.layerGroup().addTo(map)
  const bounds: L.LatLngExpression[] = []
  if (props.type === 'map_points') {
    const pts = props.data.points ?? []
    const max = Math.max(1, ...pts.map((p: any) => p.value))
    pts.forEach((p: any) => {
      bounds.push([p.lat, p.lng])
      L.circleMarker([p.lat, p.lng], { radius: 3 + Math.sqrt(p.value / max) * 9, color: '#fff', weight: 1, fillColor: growthColour(p.growth), fillOpacity: 0.8 })
        .bindTooltip(`<b>${p.name}</b><br>${p.city ?? ''}${p.province ? ', ' + p.province : ''}<br>${fmt(p.value, 'currency', false)}${p.growth !== null ? ` · ${p.growth > 0 ? '+' : ''}${p.growth}% vs LY` : ''}`)
        .on('click', () => emit('filter', { dimension: 'customer', key: p.key, label: p.name }))
        .addTo(layer!)
    })
  } else {
    const regions = (props.data.regions ?? []).filter((r: any) => r.lat !== null)
    const max = Math.max(1, ...regions.map((r: any) => r.value))
    const byGrowth = props.type === 'map_regional'
    regions.forEach((r: any) => {
      bounds.push([r.lat, r.lng])
      const radius = 12 + Math.sqrt(r.value / max) * 38
      L.circleMarker([r.lat, r.lng], { radius, color: '#fff', weight: 2, fillColor: byGrowth ? growthColour(r.growth) : '#6A0DAD', fillOpacity: byGrowth ? 0.72 : 0.55 })
        .bindTooltip(`<b>${r.name}</b><br>${fmt(r.value, 'currency', false)} · ${r.customers} customers<br>Last year ${fmt(r.last_year, 'currency', false)}${r.growth !== null ? ` · ${r.growth > 0 ? '+' : ''}${r.growth}%` : ''}`)
        .on('click', () => emit('filter', { dimension: 'province', key: r.key, label: r.name }))
        .addTo(layer!)
      L.marker([r.lat, r.lng], { interactive: false, icon: L.divIcon({ className: 'geo-label', html: `<span>${r.name}</span><b>${byGrowth && r.growth !== null ? (r.growth > 0 ? '+' : '') + r.growth + '%' : fmt(r.value)}</b>`, iconSize: [110, 34], iconAnchor: [55, 17] }) }).addTo(layer!)
    })
  }
  if (bounds.length) map.fitBounds(L.latLngBounds(bounds).pad(0.15), { maxZoom: 11 })
}

onMounted(() => {
  map = L.map(el.value!, { zoomControl: true, attributionControl: true, scrollWheelZoom: false }).setView([-29, 25], 5)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '© OpenStreetMap' }).addTo(map)
  draw()
  // Grid tiles resize after mount; keep the map filling its card.
  new ResizeObserver(() => map?.invalidateSize()).observe(el.value!)
})
watch(() => [props.data, props.type], draw)
onBeforeUnmount(() => map?.remove())
</script>

<template>
  <div class="geo">
    <div ref="el" class="geo__map" />
    <div class="geo__legend">
      <template v-if="type !== 'map_bubble'">
        <span><i :style="{ background: GOOD }" />Growing</span><span><i :style="{ background: AMBER }" />Flat</span><span><i :style="{ background: BAD }" />Declining</span>
      </template>
      <span v-else><i style="background:#6A0DAD" />Bubble size = net sales</span>
      <span v-if="data.located < data.customers" class="geo__warn">{{ data.customers - data.located }} of {{ data.customers }} customers have no location</span>
    </div>
  </div>
</template>

<style scoped>
.geo { position: relative; height: 100%; min-height: 220px; border-radius: 12px; overflow: hidden; }
.geo__map { position: absolute; inset: 0; background: #EEF0F4; }
.geo__legend { position: absolute; left: 10px; bottom: 10px; z-index: 500; display: flex; gap: 10px; flex-wrap: wrap; background: rgba(255,255,255,.92); border: 1px solid #EEEEF3; border-radius: 10px; padding: 5px 10px; font-size: 11px; color: #5B5B6E; }
.geo__legend i { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; }
.geo__warn { color: #B26A00; }
:deep(.geo-label) { display: flex; flex-direction: column; align-items: center; pointer-events: none; text-align: center; line-height: 1.15; }
:deep(.geo-label span) { font-size: 10.5px; font-weight: 700; color: #16161F; text-shadow: 0 0 3px #fff, 0 0 3px #fff; }
:deep(.geo-label b) { font-size: 11px; color: #16161F; text-shadow: 0 0 3px #fff, 0 0 3px #fff; }
:deep(.leaflet-tooltip) { border-radius: 10px; border: 1px solid #ECECF1; box-shadow: 0 8px 24px rgba(17,17,26,.08); font-family: 'Nunito Sans', sans-serif; font-size: 12px; }
</style>
