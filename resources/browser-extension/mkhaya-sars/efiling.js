// Mkhaya SARS Connect -- on eFiling, while a session is live: a bar to send the page the person is
// looking at. Nothing is sent unless they press Send; nothing on the page is clicked or changed;
// form fields (and so anything typed, such as a password or PIN) are removed before a page leaves.
// "Values to enter" lists a return already authorised in Mkhaya, to type into eFiling by hand: the
// extension never fills in, clicks or submits anything on eFiling.
(async () => {
  const status = await chrome.runtime.sendMessage({ type: 'status' }).catch(() => null)
  if (!status || status.status !== 'active') return

  const host = document.createElement('div')
  host.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:2147483647'
  host.setAttribute('data-mkhaya', 'sars-connect')
  const root = host.attachShadow({ mode: 'open' }) // the token is never in the page: it stays in the extension's own storage
  root.innerHTML = `
    <style>
      .bar{font:13px/1.4 -apple-system,Segoe UI,Arial,sans-serif;background:#6A0DAD;color:#fff;display:flex;flex-wrap:wrap;gap:10px;align-items:center;padding:8px 14px;box-shadow:0 2px 8px rgba(0,0,0,.25)}
      .bar b{color:#E4C746} select,button{font:inherit;border-radius:6px;border:0;padding:5px 10px}
      button.send{background:#00CED1;color:#06202a;font-weight:700} button.end{background:transparent;color:#fff;border:1px solid rgba(255,255,255,.6)}
      .msg{margin-left:auto;opacity:.95} .grow{flex:1}
      button.vals{background:#E4C746;color:#2a1640;font-weight:700}
      .panel{display:none;font:13px/1.4 -apple-system,Segoe UI,Arial,sans-serif;background:#fff;color:#1f1a29;max-height:60vh;overflow:auto;box-shadow:0 6px 18px rgba(0,0,0,.25);padding:10px 14px}
      .panel.open{display:block} .panel h4{margin:8px 0 4px;color:#6A0DAD} .panel table{border-collapse:collapse;width:100%} .panel td{padding:4px 6px;border-bottom:1px solid #eee}
      .panel td.v{text-align:right;font-variant-numeric:tabular-nums;font-weight:700} .panel button{background:#f1e9f8;color:#6A0DAD;padding:2px 8px} .panel .note{color:#6b6478;font-size:12px}
    </style>
    <div class="bar">
      <span>Mkhaya SARS Connect · <b class="who"></b> · <span class="left"></span></span>
      <select class="what"><option value="">What is this page?</option></select>
      <button class="vals">Values to enter</button>
      <button class="send">Send this page to Mkhaya</button>
      <button class="end">End session</button>
      <span class="msg"></span>
    </div>
    <div class="panel"></div>`
  document.documentElement.appendChild(host)
  document.documentElement.style.marginTop = '46px'
  const $ = (s) => root.querySelector(s)
  $('.who').textContent = status.taxpayer
  for (const i of status.information || []) $('.what').append(new Option(i.title, i.value))
  const tick = () => { const m = Math.max(0, Math.round((new Date(status.expires_at) - new Date()) / 60000)); $('.left').textContent = `${m} min left · ${status.pages_sent} sent` }
  tick(); setInterval(tick, 30000)

  $('.send').addEventListener('click', async () => {
    if (!$('.what').value) { $('.msg').textContent = 'Choose what this page is first.'; return }
    const copy = document.documentElement.cloneNode(true)
    copy.querySelectorAll('input,select,textarea,button,script,style,noscript,iframe,form').forEach((n) => n.remove())
    copy.querySelectorAll('[data-mkhaya]').forEach((n) => n.remove())
    $('.msg').textContent = 'Sending…'
    const r = await chrome.runtime.sendMessage({ type: 'send', page: { url: location.href, title: document.title, html: copy.outerHTML, information: $('.what').value } })
    $('.msg').textContent = r?.message || (r?.ok ? 'Sent.' : 'Could not send.')
    if (r?.outcome === 'received') { status.pages_sent++; tick() }
  })
  // The authorised returns, field by field, with a copy button for each value.
  $('.vals').addEventListener('click', async () => {
    const panel = $('.panel')
    if (panel.classList.toggle('open') && !panel.dataset.loaded) {
      panel.textContent = 'Loading…'
      const r = await chrome.runtime.sendMessage({ type: 'returns' })
      panel.textContent = ''
      panel.dataset.loaded = '1'
      const note = document.createElement('div'); note.className = 'note'
      note.textContent = 'Authorised in Mkhaya. Type these into eFiling yourself, check them, then submit on eFiling. Send the confirmation page afterwards so the SARS reference can be recorded.'
      panel.append(note)
      if (!r?.returns?.length) { const p = document.createElement('p'); p.textContent = 'No authorised return is waiting to be filed for this session.'; panel.append(p); return }
      for (const ret of r.returns) {
        const h = document.createElement('h4'); h.textContent = `${ret.reference} · ${ret.period ?? ''} · payable R ${ret.amount_payable}`; panel.append(h)
        const table = document.createElement('table')
        for (const l of ret.lines) {
          const tr = table.insertRow()
          tr.insertCell().textContent = l.field
          tr.insertCell().textContent = l.label
          const v = tr.insertCell(); v.className = 'v'; v.textContent = l.value
          const b = document.createElement('button'); b.textContent = 'Copy'
          b.addEventListener('click', async () => { await navigator.clipboard.writeText(l.value).catch(() => {}); b.textContent = 'Copied' ; setTimeout(() => { b.textContent = 'Copy' }, 1500) })
          tr.insertCell().append(b)
        }
        panel.append(table)
      }
    }
  })
  $('.end').addEventListener('click', async () => {
    await chrome.runtime.sendMessage({ type: 'end' })
    host.remove(); document.documentElement.style.marginTop = ''
  })
})()
