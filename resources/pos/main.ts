import { createApp } from 'vue'
import App from './App.vue'

createApp(App).mount('#pos')

// Offline support: the service worker serves the app shell and assets when there is no network.
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/pos-sw.js', { scope: '/pos' }).catch((e) => console.warn('service worker', e))
}
