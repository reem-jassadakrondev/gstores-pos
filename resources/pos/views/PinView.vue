<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import PinPad from '../components/PinPad.vue'
import { api } from '../lib/api'
import { db, getMeta, setMeta, type Staff } from '../lib/db'
import { verifyPin } from '../lib/pin'
import { state } from '../lib/state'

/** Staff sign in with a PIN. Checked offline against the cached verifier; 5 misses lock for 5 minutes. */
const staff = ref<Staff[]>([])
const chosen = ref<Staff | null>(null)
const error = ref<string | null>(null)
const busy = ref(false)

async function load() {
  staff.value = (await db.staff.toArray()).filter((s) => s.permissions.length).sort((a, b) => a.name.localeCompare(b.name, 'th'))
}
onMounted(load)
watch(() => state.lastSync, load)

async function login(pin: string) {
  const who = chosen.value!
  const lockKey = `pin_lock_${who.id}`
  const lock = (await getMeta<{ failed: number; until: number }>(lockKey)) ?? { failed: 0, until: 0 }
  if (lock.until > Date.now()) {
    error.value = `ใส่ PIN ผิดหลายครั้ง รออีก ${Math.ceil((lock.until - Date.now()) / 1000)} วินาที`
    return
  }
  busy.value = true
  error.value = null
  let ok = false
  try {
    if (who.pin) ok = await verifyPin(pin, who.pin)
    // PINs set before offline login existed have no verifier yet: check online.
    else ok = !!(await api('pin-login', { method: 'POST', body: { user_id: who.id, pin } }).catch(() => null))
  } finally {
    busy.value = false
  }
  if (!ok) {
    const failed = lock.failed + 1
    await setMeta(lockKey, { failed: failed >= 5 ? 0 : failed, until: failed >= 5 ? Date.now() + 300_000 : 0 })
    error.value = who.pin ? 'PIN ไม่ถูกต้อง' : 'PIN ไม่ถูกต้อง หรือออฟไลน์ (ผู้ใช้นี้ต้องตั้ง PIN ใหม่เพื่อใช้แบบออฟไลน์)'
    return
  }
  await setMeta(lockKey, { failed: 0, until: 0 })
  state.staff = who
}
</script>

<template>
  <div class="flex min-h-full items-center justify-center p-6">
    <div class="card w-full max-w-md space-y-4 p-6">
      <h1 class="text-center text-xl font-bold">{{ state.terminal?.store?.name }} · {{ state.terminal?.terminal?.name }}</h1>
      <template v-if="!chosen">
        <p class="text-center text-slate-600">เลือกชื่อของคุณ</p>
        <p v-if="!staff.length" class="text-center text-rose-600">ยังไม่มีพนักงานที่ตั้ง PIN สำหรับสาขานี้ (ตั้งได้ที่หลังบ้าน → ผู้ใช้และพนักงาน)</p>
        <div class="grid grid-cols-2 gap-2">
          <button v-for="s in staff" :key="s.id" class="btn-ghost py-5 text-lg" @click="chosen = s">{{ s.name }}</button>
        </div>
      </template>
      <template v-else>
        <p class="text-center text-lg">สวัสดี <b>{{ chosen.name }}</b> ใส่ PIN</p>
        <PinPad :error="error" :busy="busy" @submit="login" />
        <button class="w-full text-slate-500" @click="chosen = null; error = null">← เปลี่ยนผู้ใช้</button>
      </template>
    </div>
  </div>
</template>
