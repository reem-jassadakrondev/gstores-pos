<script setup lang="ts">
import { onMounted } from 'vue'
import { db, getMeta } from './lib/db'
import { state } from './lib/state'
import { pull, pushOutbox, refreshCounts, startSyncLoop } from './lib/sync'
import HistoryView from './views/HistoryView.vue'
import OpenShiftView from './views/OpenShiftView.vue'
import PairView from './views/PairView.vue'
import PinView from './views/PinView.vue'
import SellView from './views/SellView.vue'
import SettingsView from './views/SettingsView.vue'
import ShiftView from './views/ShiftView.vue'

/** Flow: pair device → staff PIN → open shift → sell. Works offline after the first pairing. */
onMounted(async () => {
  state.paired = !!(await getMeta('token'))
  state.terminal = await getMeta('terminal')
  state.shift = (await db.shifts.filter((s) => !s.closed_at).first()) ?? null
  await refreshCounts()
  state.ready = true
  startSyncLoop()
  if (state.paired) {
    await pushOutbox()
    await pull()
    adoptServerShift()
  }
})

/** Reinstalled device: continue the shift the server knows is still open. */
async function adoptServerShift() {
  const open = state.terminal?.sequences?.open_shift
  if (!state.shift && open && !(await db.shifts.get(open.id))) {
    const shift = { ...open, opening_cash: Number(open.opening_cash), cash_movements: [] }
    await db.shifts.put(shift)
    state.shift = shift
  }
}

function lock() {
  state.staff = null
  state.screen = 'sell'
}

const tabs = [
  ['sell', 'ขาย'],
  ['history', 'บิล'],
  ['shift', 'กะ'],
  ['settings', 'ตั้งค่า'],
] as const
</script>

<template>
  <div v-if="!state.ready" class="flex h-full items-center justify-center text-slate-400">กำลังโหลด…</div>
  <PairView v-else-if="!state.paired || !state.terminal" />
  <PinView v-else-if="!state.staff" />
  <OpenShiftView v-else-if="!state.shift" />
  <div v-else class="flex h-full flex-col">
    <header class="flex items-center gap-2 bg-slate-900 px-3 py-2 text-white">
      <b class="text-violet-300">ReemoPOS</b>
      <nav class="ml-3 flex gap-1">
        <button v-for="[key, label] in tabs" :key="key" class="rounded-lg px-4 py-2"
          :class="state.screen === key ? 'bg-white/20 font-semibold' : 'opacity-70'" @click="state.screen = key">{{ label }}</button>
      </nav>
      <span class="ml-auto flex items-center gap-2 text-sm">
        <span class="h-2.5 w-2.5 rounded-full" :class="state.online ? 'bg-emerald-400' : 'bg-rose-500'" />
        {{ state.online ? 'ออนไลน์' : 'ออฟไลน์' }}
        <span v-if="state.pending" class="rounded-full bg-amber-500 px-2">รอส่ง {{ state.pending }}</span>
        <span v-if="state.failed" class="rounded-full bg-rose-600 px-2">ผิดพลาด {{ state.failed }}</span>
      </span>
      <span class="ml-3 text-sm opacity-80">{{ state.staff.name }}</span>
      <button class="rounded-lg bg-white/10 px-3 py-2 text-sm" @click="lock">ล็อก</button>
    </header>
    <main class="min-h-0 flex-1">
      <SellView v-if="state.screen === 'sell'" />
      <HistoryView v-else-if="state.screen === 'history'" />
      <ShiftView v-else-if="state.screen === 'shift'" />
      <SettingsView v-else />
    </main>
  </div>
</template>
