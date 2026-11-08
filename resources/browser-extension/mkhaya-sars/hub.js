// Mkhaya SARS Connect -- on the Mkhaya page: say the extension is here, and pass on a session the
// person starts there. Nothing else on the page is read.
const EXT = 'mkhaya-sars-extension'
const announce = () => window.postMessage({ source: EXT, type: 'present', version: chrome.runtime.getManifest().version }, window.location.origin)
announce()
window.addEventListener('load', announce)
window.addEventListener('message', (e) => {
  if (e.source !== window || e.origin !== window.location.origin || e.data?.source !== 'mkhaya-sars' ) return
  if (e.data.type === 'ping') return announce()
  if (e.data.type === 'start') {
    chrome.runtime.sendMessage({ type: 'start', session: e.data.session }, (r) => window.postMessage({ source: EXT, type: 'started', ok: !!r?.ok, message: r?.message }, window.location.origin))
  }
})
