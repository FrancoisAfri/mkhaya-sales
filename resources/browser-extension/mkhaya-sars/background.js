// Mkhaya SARS Connect -- holds the one live session. The token lives in session storage only (gone
// when the browser closes) and is sent only to the Mkhaya address it came from.
const KEY = 'mkhayaSarsSession'

async function current() {
  const { [KEY]: s } = await chrome.storage.session.get(KEY)
  if (!s) return null
  if (new Date(s.expires_at) < new Date()) { await chrome.storage.session.remove(KEY); return null }
  return s
}

async function call(s, path, method = 'GET', body = null) {
  const res = await fetch(`${s.api}${path}`, { method, headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: body ? JSON.stringify(body) : null, credentials: 'omit' })
  const data = await res.json().catch(() => ({}))
  return { ok: res.ok, http: res.status, ...data }
}

chrome.runtime.onMessage.addListener((msg, sender, reply) => {
  (async () => {
    if (msg.type === 'start') {
      // Only a Mkhaya page this extension was built for can start a session, and only for its own address.
      if (!sender.url || !msg.session?.api?.startsWith(new URL(sender.url).origin)) return reply({ ok: false, message: 'Not a Mkhaya page.' })
      await chrome.storage.session.set({ [KEY]: msg.session })
      chrome.tabs.create({ url: msg.session.efiling })
      return reply({ ok: true })
    }
    const s = await current()
    if (!s) return reply({ ok: false, status: 'none' })
    if (msg.type === 'status') {
      const r = await call(s, '')
      // Ended, run out or revoked in Mkhaya: forget it here too.
      if (r.status !== 'active') await chrome.storage.session.remove(KEY)
      return reply(r)
    }
    if (msg.type === 'send') return reply(await call(s, '/pages', 'POST', msg.page))
    if (msg.type === 'returns') return reply(await call(s, '/returns'))
    if (msg.type === 'end') {
      const r = await call(s, '/end', 'POST')
      await chrome.storage.session.remove(KEY)
      return reply(r)
    }
    reply({ ok: false })
  })()
  return true
})
