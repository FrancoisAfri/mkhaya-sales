<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const props = defineProps<{ mailboxes: any[]; runs: any[]; layouts: { id: number; name: string }[]; timezones: string[] }>()

const days = [{ v: 1, t: 'Mon' }, { v: 2, t: 'Tue' }, { v: 3, t: 'Wed' }, { v: 4, t: 'Thu' }, { v: 5, t: 'Fri' }, { v: 6, t: 'Sat' }, { v: 7, t: 'Sun' }]
const dialog = ref(false)
const editing = ref<any>(null)
const blank = () => ({
  name: 'Daily sales report', provider: 'graph', mailbox_address: '', folder: '', sender: '', subject_pattern: '', filename_pattern: '',
  extensions: 'xlsx,xls,csv', layout_id: null as number | null, run_time: '03:30', timezone: 'Africa/Johannesburg', run_days: [] as number[],
  lookback_days: 2, missing_after_minutes: 120, auto_commit: true, enabled: true,
  credentials: { tenant_id: '', client_id: '', client_secret: '', host: '', port: 993, encryption: 'ssl', password: '', private_key: '', host_fingerprint: '', drive_id: '',
    db_driver: 'sqlsrv', database: '', odbc_dsn: '', query: '', url: '', auth_type: 'bearer', header_name: 'X-API-Key', rows_path: '' } as Record<string, any>,
})
const form = useForm(blank())

function open(m: any = null) {
  editing.value = m
  const b = blank()
  Object.assign(form, m ? { ...b, ...Object.fromEntries(Object.keys(b).filter(k => k !== 'credentials').map(k => [k, m[k] ?? (b as any)[k]])), run_days: m.run_days ?? [], credentials: b.credentials } : b)
  form.clearErrors()
  dialog.value = true
}
function save() {
  const opts = { preserveScroll: true, onSuccess: () => { dialog.value = false } }
  editing.value ? form.put(`/sales/mailboxes/${editing.value.uuid}`, opts) : form.post('/sales/mailboxes', opts)
}
// Method 1 = email (Microsoft 365 or IMAP); method 2 = a secure central repository (SFTP or SharePoint).
const providers = [
  { value: 'graph', title: 'Email — Microsoft 365 (Graph API, recommended)', icon: 'tabler-brand-windows', label: 'Microsoft 365' },
  { value: 'imap', title: 'Email — IMAP', icon: 'tabler-mail', label: 'IMAP' },
  { value: 'sftp', title: 'Central repository — SFTP server', icon: 'tabler-server-2', label: 'SFTP repository' },
  { value: 'sharepoint', title: 'Central repository — SharePoint / OneDrive folder', icon: 'tabler-folder-share', label: 'SharePoint repository' },
  { value: 'database', title: 'Live connection — database (SQL Server, MySQL, PostgreSQL, ODBC)', icon: 'tabler-database', label: 'Database' },
  { value: 'api', title: 'Live connection — web API (JSON or CSV)', icon: 'tabler-api', label: 'Web API' },
]
const prov = (v: string) => providers.find(p => p.value === v) ?? providers[0]
const isRepo = (v: string) => ['sftp', 'sharepoint', 'database', 'api'].includes(v)
const isLive = (v: string) => v === 'database' || v === 'api'
watch(() => form.provider, (v) => { if (v === 'sftp' && (!form.credentials.port || form.credentials.port === 993)) form.credentials.port = 22; if (v === 'imap' && form.credentials.port === 22) form.credentials.port = 993 })
const when = (s: string | null) => s ? new Date(s).toLocaleString('en-ZA', { dateStyle: 'medium', timeStyle: 'short' }) : '—'
const health: Record<string, string> = { healthy: 'success', failing: 'error', new: 'info', disabled: 'default' }
const runColor: Record<string, string> = { imported: 'success', no_email: 'warning', duplicate: 'secondary', failed: 'error', running: 'info' }
</script>

<template>
  <VContainer fluid class="pa-4 pa-sm-6">
    <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
      <div>
        <h3 class="text-h5 font-weight-bold mb-1">Report sources</h3>
        <p class="text-body-2 text-medium-emphasis mb-0">The daily report is collected automatically at the agreed time — by email (method 1) or from a secure central repository (method 2). If it does not arrive, load the file in the <a href="/sales/imports">Import Centre</a>.</p>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" @click="open()">Add source</VBtn>
    </div>

    <VRow>
      <VCol v-for="m in mailboxes" :key="m.id" cols="12" lg="6">
        <VCard elevation="0" border rounded="lg">
          <VCardItem>
            <template #prepend><VIcon :icon="prov(m.provider).icon" size="28" /></template>
            <VCardTitle>{{ m.name }}</VCardTitle>
            <VCardSubtitle>{{ m.mailbox_address }} · {{ prov(m.provider).label }} · {{ m.folder || '/' }}</VCardSubtitle>
            <template #append><VChip :color="health[m.status]" variant="tonal" size="small">{{ m.status }}</VChip></template>
          </VCardItem>
          <VCardText>
            <VAlert v-if="m.last_error" type="error" variant="tonal" density="compact" class="mb-3">{{ m.last_error }}</VAlert>
            <VRow dense>
              <VCol cols="6" md="4"><div class="text-caption text-medium-emphasis">Runs at</div><div>{{ m.run_time }} ({{ m.timezone }})</div></VCol>
              <VCol cols="6" md="4"><div class="text-caption text-medium-emphasis">Next run</div><div>{{ when(m.next_run) }}</div></VCol>
              <VCol cols="6" md="4"><div class="text-caption text-medium-emphasis">Last connected</div><div>{{ when(m.last_connected_at) }}</div></VCol>
              <VCol cols="6" md="4"><div class="text-caption text-medium-emphasis">Last report email</div><div>{{ when(m.last_email_at) }}</div></VCol>
              <VCol cols="6" md="4"><div class="text-caption text-medium-emphasis">Last successful import</div><div>{{ when(m.stats.last_success) }}</div></VCol>
              <VCol cols="6" md="4"><div class="text-caption text-medium-emphasis">Layout</div><div>{{ m.layout?.name ?? 'match by headings' }}</div></VCol>
              <VCol cols="4"><div class="text-caption text-medium-emphasis">Imported</div><div class="text-h6 text-success">{{ m.stats.imported }}</div></VCol>
              <VCol cols="4"><div class="text-caption text-medium-emphasis">Failed</div><div class="text-h6 text-error">{{ m.stats.failed }}</div></VCol>
              <VCol cols="4"><div class="text-caption text-medium-emphasis">Awaiting review</div><div class="text-h6 text-warning">{{ m.stats.awaiting }}</div></VCol>
            </VRow>
            <div class="text-caption text-medium-emphasis mt-2">Only emails from <strong>{{ m.sender || 'any sender' }}</strong><span v-if="m.subject_pattern"> with subject “{{ m.subject_pattern }}”</span>, attachments {{ m.extensions }}<span v-if="m.filename_pattern"> named {{ m.filename_pattern }}</span>.</div>
          </VCardText>
          <VCardActions>
            <VBtn variant="text" prepend-icon="tabler-plug-connected" @click="router.post(`/sales/mailboxes/${m.uuid}/test`, {}, { preserveScroll: true })">Test connection</VBtn>
            <VBtn variant="text" prepend-icon="tabler-player-play" @click="router.post(`/sales/mailboxes/${m.uuid}/run`, {}, { preserveScroll: true })">Run now</VBtn>
            <VSpacer />
            <VBtn variant="tonal" prepend-icon="tabler-settings" @click="open(m)">Settings</VBtn>
          </VCardActions>
        </VCard>
      </VCol>
      <VCol v-if="!mailboxes.length" cols="12"><VCard elevation="0" border rounded="lg"><VCardText class="text-center text-medium-emphasis py-8">No mailbox configured yet.</VCardText></VCard></VCol>
    </VRow>

    <VCard elevation="0" border rounded="lg" class="mt-6" title="Processing log">
      <VTable density="compact">
        <thead><tr><th>Started</th><th>Trigger</th><th>Result</th><th>Email</th><th>Attachment</th><th>Import</th><th>Error</th></tr></thead>
        <tbody>
          <tr v-for="r in runs" :key="r.id">
            <td class="text-no-wrap">{{ when(r.started_at) }}</td>
            <td>{{ r.trigger }}<span v-if="r.attempt > 1"> #{{ r.attempt }}</span></td>
            <td><VChip size="x-small" :color="runColor[r.status]" variant="tonal">{{ r.status }}</VChip></td>
            <td class="text-caption">{{ r.subject ?? '—' }}<br><span class="text-medium-emphasis">{{ r.sender }}</span></td>
            <td class="text-caption">{{ r.attachment_name ?? '—' }}</td>
            <td><a v-if="r.batch" :href="`/sales/imports/${r.batch.uuid}`">{{ r.batch.status }} ({{ r.batch.inserted_rows }} added)</a><span v-else>—</span></td>
            <td class="text-caption text-error">{{ r.error }}</td>
          </tr>
          <tr v-if="!runs.length"><td colspan="7" class="text-medium-emphasis">No runs yet.</td></tr>
        </tbody>
      </VTable>
    </VCard>

    <VDialog v-model="dialog" max-width="860" scrollable>
      <VCard :title="editing ? `Mailbox — ${editing.name}` : 'Add mailbox'">
        <VCardText>
          <VRow dense>
            <VCol cols="12" md="6"><VTextField v-model="form.name" label="Name *" density="compact" variant="outlined" :error-messages="form.errors.name" /></VCol>
            <VCol cols="12" md="6"><VSelect v-model="form.provider" :items="providers" label="How the report arrives" density="compact" variant="outlined" /></VCol>
            <VCol v-if="form.provider !== 'api'" cols="12" md="6"><VTextField v-model="form.mailbox_address" :label="({ graph: 'Reporting mailbox address *', imap: 'IMAP login (email) *', sftp: 'SFTP user name *', sharepoint: 'SharePoint site * (e.g. contoso.sharepoint.com/sites/Sales)', database: 'Database user (read-only) *' } as any)[form.provider]" density="compact" variant="outlined" :error-messages="form.errors.mailbox_address" /></VCol>
            <VCol v-if="!isLive(form.provider)" cols="12" md="6"><VTextField v-model="form.folder" :label="isRepo(form.provider) ? 'Folder on the repository' : 'Mail folder'" :placeholder="({ graph: 'inbox', imap: 'INBOX', sftp: '/outgoing/sales', sharepoint: 'Shared Documents/Daily sales' } as any)[form.provider]" density="compact" variant="outlined" /></VCol>

            <template v-if="form.provider === 'graph' || form.provider === 'sharepoint'">
              <VCol v-if="form.provider === 'sharepoint'" cols="12"><VAlert type="info" variant="tonal" density="compact">Register an app in Microsoft Entra ID with <strong>Sites.Selected</strong> (granted on this one site) or <strong>Files.Read.All</strong>. Files are only read — nothing is moved or deleted. Secrets are stored encrypted.</VAlert></VCol>
              <VCol v-if="form.provider === 'graph'" cols="12"><VAlert type="info" variant="tonal" density="compact">Register an app in Microsoft Entra ID with the application permission <strong>Mail.Read</strong> only, and ask the Exchange admin to limit it to this one mailbox (Application Access Policy). Secrets are stored encrypted; leave blank to keep the saved value.</VAlert></VCol>
              <VCol cols="12" md="4"><VTextField v-model="form.credentials.tenant_id" label="Directory (tenant) ID" :placeholder="editing?.credential_keys?.includes('tenant_id') ? '•••• saved' : ''" density="compact" variant="outlined" /></VCol>
              <VCol cols="12" md="4"><VTextField v-model="form.credentials.client_id" label="Application (client) ID" :placeholder="editing?.credential_keys?.includes('client_id') ? '•••• saved' : ''" density="compact" variant="outlined" /></VCol>
              <VCol cols="12" md="4"><VTextField v-model="form.credentials.client_secret" type="password" label="Client secret" :placeholder="editing?.credential_keys?.includes('client_secret') ? '•••• saved' : ''" density="compact" variant="outlined" /></VCol>
              <VCol v-if="form.provider === 'sharepoint'" cols="12"><VTextField v-model="form.credentials.drive_id" label="Drive ID (optional — only if the files are not in the site's main document library)" density="compact" variant="outlined" /></VCol>
            </template>
            <template v-else-if="form.provider === 'database'">
              <VCol cols="12"><VAlert type="info" variant="tonal" density="compact">Reads sales straight from the client's database with one saved SELECT. Use a read-only database user. Only a single SELECT statement is accepted; <code>:from</code> and <code>:to</code> in the query become the look-back dates. The result is imported like a file, through the layout chosen below.</VAlert></VCol>
              <VCol cols="12" md="4"><VSelect v-model="form.credentials.db_driver" :items="[{ value: 'sqlsrv', title: 'SQL Server' }, { value: 'mysql', title: 'MySQL / MariaDB' }, { value: 'pgsql', title: 'PostgreSQL' }, { value: 'odbc', title: 'ODBC (e.g. SAP HANA)' }]" label="Database type" density="compact" variant="outlined" /></VCol>
              <template v-if="form.credentials.db_driver !== 'odbc'">
                <VCol cols="12" md="4"><VTextField v-model="form.credentials.host" label="Server" :placeholder="editing?.credential_keys?.includes('host') ? '•••• saved' : 'db.client.co.za'" density="compact" variant="outlined" /></VCol>
                <VCol cols="6" md="2"><VTextField v-model.number="form.credentials.port" label="Port" type="number" density="compact" variant="outlined" /></VCol>
                <VCol cols="6" md="2"><VTextField v-model="form.credentials.database" label="Database" density="compact" variant="outlined" /></VCol>
              </template>
              <VCol v-else cols="12" md="8"><VTextField v-model="form.credentials.odbc_dsn" label="ODBC connection string" placeholder="DSN=SAPHANA;SERVERNODE=…" density="compact" variant="outlined" /></VCol>
              <VCol cols="12" md="6"><VTextField v-model="form.credentials.password" type="password" label="Password" :placeholder="editing?.credential_keys?.includes('password') ? '•••• saved' : ''" density="compact" variant="outlined" /></VCol>
              <VCol cols="12"><VTextarea v-model="form.credentials.query" rows="4" label="Query (one SELECT)" :placeholder="editing?.credential_keys?.includes('query') ? '•••• saved — type a new query to replace it' : 'SELECT InvDate, AccNo, AccName, Material, Cases, NettValue FROM SalesLines WHERE InvDate BETWEEN :from AND :to'" density="compact" variant="outlined" :error-messages="form.errors['credentials.query']" /></VCol>
            </template>
            <template v-else-if="form.provider === 'api'">
              <VCol cols="12"><VAlert type="info" variant="tonal" density="compact">Reads sales from a web API over HTTPS — JSON rows or a CSV. <code>{from}</code> and <code>{to}</code> in the address become the look-back dates. The rows are imported like a file, through the layout chosen below.</VAlert></VCol>
              <VCol cols="12"><VTextField v-model="form.credentials.url" label="Address *" :placeholder="editing?.credential_keys?.includes('url') ? '•••• saved' : 'https://api.client.co.za/sales?from={from}&to={to}'" density="compact" variant="outlined" :error-messages="form.errors['credentials.url']" /></VCol>
              <VCol cols="12" md="4"><VSelect v-model="form.credentials.auth_type" :items="[{ value: 'bearer', title: 'Bearer token' }, { value: 'header', title: 'API key header' }, { value: 'none', title: 'No sign-in' }]" label="Sign-in" density="compact" variant="outlined" /></VCol>
              <VCol v-if="form.credentials.auth_type === 'header'" cols="12" md="3"><VTextField v-model="form.credentials.header_name" label="Header name" density="compact" variant="outlined" /></VCol>
              <VCol v-if="form.credentials.auth_type !== 'none'" cols="12" md="5"><VTextField v-model="form.credentials.password" type="password" label="Token / key" :placeholder="editing?.credential_keys?.includes('password') ? '•••• saved' : ''" density="compact" variant="outlined" /></VCol>
              <VCol cols="12" md="6"><VTextField v-model="form.credentials.rows_path" label="Where the rows are (optional)" placeholder="data.items" hint="Leave empty when the API returns a plain list" persistent-hint density="compact" variant="outlined" /></VCol>
            </template>
            <template v-else-if="form.provider === 'sftp'">
              <VCol cols="12"><VAlert type="info" variant="tonal" density="compact">Secure central repository over SFTP. Use a read-only account limited to the report folder; a key is safer than a password. Files are only read — nothing is moved or deleted. Secrets are stored encrypted; leave blank to keep the saved value.</VAlert></VCol>
              <VCol cols="12" md="5"><VTextField v-model="form.credentials.host" label="SFTP server" :placeholder="editing?.credential_keys?.includes('host') ? '•••• saved' : 'sftp.example.co.za'" density="compact" variant="outlined" /></VCol>
              <VCol cols="6" md="2"><VTextField v-model.number="form.credentials.port" label="Port" type="number" density="compact" variant="outlined" /></VCol>
              <VCol cols="12" md="5"><VTextField v-model="form.credentials.password" type="password" label="Password (or key passphrase)" :placeholder="editing?.credential_keys?.includes('password') ? '•••• saved' : ''" density="compact" variant="outlined" /></VCol>
              <VCol cols="12" md="7"><VTextarea v-model="form.credentials.private_key" rows="2" label="Private key (optional, OpenSSH/PEM)" :placeholder="editing?.credential_keys?.includes('private_key') ? '•••• saved' : '-----BEGIN OPENSSH PRIVATE KEY-----'" density="compact" variant="outlined" /></VCol>
              <VCol cols="12" md="5"><VTextField v-model="form.credentials.host_fingerprint" label="Server key fingerprint (optional)" placeholder="SHA256:…" hint="Refuses to connect if the server's key changes" persistent-hint density="compact" variant="outlined" /></VCol>
            </template>
            <template v-else>
              <VCol cols="12" md="5"><VTextField v-model="form.credentials.host" label="IMAP server" :placeholder="editing?.credential_keys?.includes('host') ? '•••• saved' : 'imap.example.com'" density="compact" variant="outlined" /></VCol>
              <VCol cols="6" md="2"><VTextField v-model.number="form.credentials.port" label="Port" type="number" density="compact" variant="outlined" /></VCol>
              <VCol cols="6" md="2"><VSelect v-model="form.credentials.encryption" :items="['ssl', 'tls', 'none']" label="Security" density="compact" variant="outlined" /></VCol>
              <VCol cols="12" md="3"><VTextField v-model="form.credentials.password" type="password" label="Password" :placeholder="editing?.credential_keys?.includes('password') ? '•••• saved' : ''" density="compact" variant="outlined" /></VCol>
            </template>

            <VCol cols="12"><VDivider class="my-2" /><div class="text-subtitle-2">{{ isRepo(form.provider) ? 'Which file is the report' : 'Which email is the report' }}</div></VCol>
            <VCol v-if="!isRepo(form.provider)" cols="12" md="4"><VTextField v-model="form.sender" label="Approved sender" placeholder="reports@vector.co.za or @vector.co.za" density="compact" variant="outlined" /></VCol>
            <VCol v-if="!isRepo(form.provider)" cols="12" md="4"><VTextField v-model="form.subject_pattern" label="Subject contains (or /regex/)" placeholder="Daily Sales" density="compact" variant="outlined" /></VCol>
            <VCol v-if="!isLive(form.provider)" cols="12" md="4"><VTextField v-model="form.filename_pattern" :label="isRepo(form.provider) ? 'File name pattern' : 'Attachment name pattern'" placeholder="*Sales*.xlsx" density="compact" variant="outlined" /></VCol>
            <VCol cols="12" md="4"><VTextField v-model="form.extensions" label="Allowed file types" density="compact" variant="outlined" /></VCol>
            <VCol cols="12" md="8"><VSelect v-model="form.layout_id" :items="layouts" item-title="name" item-value="id" clearable label="File layout for this report" density="compact" variant="outlined" /></VCol>

            <VCol cols="12"><VDivider class="my-2" /><div class="text-subtitle-2">When to collect it</div></VCol>
            <VCol cols="6" md="3"><VTextField v-model="form.run_time" type="time" label="Run at *" density="compact" variant="outlined" :error-messages="form.errors.run_time" /></VCol>
            <VCol cols="6" md="4"><VSelect v-model="form.timezone" :items="timezones" label="Time zone" density="compact" variant="outlined" /></VCol>
            <VCol cols="12" md="5">
              <div class="text-caption text-medium-emphasis">Days (none selected = every day)</div>
              <VChipGroup v-model="form.run_days" multiple column><VChip v-for="d in days" :key="d.v" :value="d.v" filter size="small">{{ d.t }}</VChip></VChipGroup>
            </VCol>
            <VCol cols="6" md="3"><VTextField v-model.number="form.lookback_days" type="number" label="Look back (days)" density="compact" variant="outlined" /></VCol>
            <VCol cols="6" md="4"><VTextField v-model.number="form.missing_after_minutes" type="number" label="Alert if missing after (minutes)" density="compact" variant="outlined" /></VCol>
            <VCol cols="12" md="5"><VSwitch v-model="form.auto_commit" label="Commit automatically when every row is valid" density="compact" hide-details inset /></VCol>
            <VCol cols="12"><VSwitch v-model="form.enabled" label="Enabled" density="compact" hide-details inset color="success" /></VCol>
          </VRow>
        </VCardText>
        <VCardActions><VSpacer /><VBtn variant="text" @click="dialog = false">Cancel</VBtn><VBtn color="primary" :loading="form.processing" @click="save">Save</VBtn></VCardActions>
      </VCard>
    </VDialog>
  </VContainer>
</template>
