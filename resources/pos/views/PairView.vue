<script setup lang="ts">
import { ref } from 'vue'
import { api, ApiError } from '../lib/api'
import { setMeta } from '../lib/db'
import { state } from '../lib/state'
import { pull, rememberTerminal } from '../lib/sync'

/** First run: swap the one-time pairing code from the back-office for this device's token. */
const code = ref('')
const error = ref<string | null>(null)
const busy = ref(false)

function deviceType() {
  const ua = navigator.userAgent
  if (/android/i.test(ua)) return 'android'
  if (/iPad|iPhone|Macintosh.*Mobile/i.test(ua) || (navigator.maxTouchPoints > 1 && /Mac/.test(ua))) return 'ios'
  if (/Windows/i.test(ua)) return 'windows'
  if (/Mac/i.test(ua)) return 'mac'
  return 'web'
}

async function pair() {
  busy.value = true
  error.value = null
  try {
    const res = await api('pair', { method: 'POST', body: { code: code.value, device_type: deviceType() }, token: '' })
    await setMeta('token', res.token)
    await setMeta('catalog_synced_at', null)
    await rememberTerminal(res)
    // Download staff and catalog before leaving this screen, so the PIN screen has names to show.
    await pull(true)
    state.paired = true
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'เชื่อมต่อเซิร์ฟเวอร์ไม่ได้ ต้องใช้อินเทอร์เน็ตตอนจับคู่ครั้งแรก'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="flex min-h-full items-center justify-center p-6">
    <form class="card w-full max-w-sm space-y-4 p-6" @submit.prevent="pair">
      <h1 class="text-center text-2xl font-bold text-brand">ReemoPOS</h1>
      <p class="text-center text-slate-600">ใส่รหัสจับคู่จากหลังบ้าน (เมนู ร้านค้า → เครื่อง POS → รหัสจับคู่)</p>
      <input v-model="code" class="input text-center font-mono text-2xl uppercase tracking-widest" placeholder="XXXX-XXXX"
        autocomplete="off" autofocus maxlength="9">
      <p v-if="error" class="text-center text-rose-600">{{ error }}</p>
      <button class="btn-primary w-full" :disabled="code.length < 8 || busy">{{ busy ? 'กำลังเชื่อมต่อ…' : 'จับคู่เครื่อง' }}</button>
    </form>
  </div>
</template>
