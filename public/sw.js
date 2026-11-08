// Mkhaya Sales does not use a service worker. This file exists so that a browser still holding one
// registered for this address (for example from another app once served on the same localhost
// port) replaces it with this, which unregisters itself and leaves the page alone.
self.addEventListener('install', () => self.skipWaiting())
self.addEventListener('activate', (event) => {
  event.waitUntil(self.registration.unregister())
})
