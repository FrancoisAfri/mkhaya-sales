<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'

defineProps<{ dashboards: any[]; templates: { key: string; name: string; widgets: number }[]; canEdit: boolean }>()
const dialog = ref(false)
const form = useForm({ name: '', template: null as string | null })
const icons: Record<string, string> = { executive: 'tabler-crown', customer: 'tabler-users', product: 'tabler-package', target: 'tabler-target-arrow', historical: 'tabler-history',
  growth: 'tabler-trending-up', regional: 'tabler-map-2', forecasting: 'tabler-crystal-ball', quality: 'tabler-shield-check', management: 'tabler-briefcase' }
function start(t: any = null) { form.name = t ? t.name : ''; form.template = t?.key ?? null; dialog.value = true }
const vis: Record<string, string> = { private: 'Only me', shared: 'Shared', organisation: 'Organisation' }
</script>

<template>
  <div class="bi-index">
    <header class="d-flex justify-space-between align-end flex-wrap gap-3 mb-6">
      <div><h1>Dashboards</h1><p>Interactive dashboards built from the sales measure engine. Open one, or build your own.</p></div>
      <VBtn v-if="canEdit" color="primary" size="large" prepend-icon="tabler-plus" @click="start()">New dashboard</VBtn>
    </header>

    <section class="mb-10">
      <h2>Your dashboards</h2>
      <div class="grid">
        <a v-for="d in dashboards" :key="d.uuid" :href="`/sales/bi/${d.uuid}`" class="tile">
          <VIcon :icon="icons[d.template_key] ?? 'tabler-layout-dashboard'" size="24" />
          <strong>{{ d.name }}</strong>
          <small>{{ vis[d.visibility] }}<span v-if="d.role_landing"> · landing page</span></small>
        </a>
        <p v-if="!dashboards.length" class="muted">None yet — start from a template below.</p>
      </div>
    </section>

    <section v-if="canEdit">
      <h2>Start from a template</h2>
      <div class="grid">
        <button v-for="t in templates" :key="t.key" class="tile tile--tpl" @click="start(t)">
          <VIcon :icon="icons[t.key]" size="24" /><strong>{{ t.name }}</strong><small>{{ t.widgets }} visuals · customise freely</small>
        </button>
      </div>
    </section>

    <VDialog v-model="dialog" max-width="460">
      <VCard :title="form.template ? 'Copy template' : 'New dashboard'">
        <VCardText><VTextField v-model="form.name" label="Dashboard name" density="comfortable" variant="outlined" autofocus :error-messages="form.errors.name" /></VCardText>
        <VCardActions><VSpacer /><VBtn variant="text" @click="dialog = false">Cancel</VBtn><VBtn color="primary" :loading="form.processing" @click="form.post('/sales/bi')">Create</VBtn></VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.bi-index { padding: 32px 36px 56px; background: #F7F7FA; min-height: calc(100vh - 140px); }
h1 { font-size: 30px; font-weight: 700; letter-spacing: -.6px; margin: 0; color: #16161F; }
header p { margin: 6px 0 0; color: #77778A; }
h2 { font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: #8A8A99; margin: 0 0 14px; font-weight: 700; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 16px; }
.tile { display: flex; flex-direction: column; gap: 8px; padding: 22px; background: #fff; border: 1px solid #EEEEF3; border-radius: 20px; text-decoration: none; color: #6A0DAD; text-align: left; cursor: pointer; transition: all .18s; }
.tile:hover { box-shadow: 0 14px 34px rgba(17,17,26,.07); transform: translateY(-2px); border-color: #DCCDEF; }
.tile strong { color: #16161F; font-size: 15px; } .tile small { color: #8A8A99; font-size: 12px; }
.tile--tpl { background: linear-gradient(180deg, #fff 0%, #FBF9FE 100%); }
.muted { color: #8A8A99; }
</style>
