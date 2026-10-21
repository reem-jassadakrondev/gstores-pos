<script setup lang="ts">
import { ref } from 'vue'
import NumPad from '../components/NumPad.vue'
import { db, type LocalShift } from '../lib/db'
import { can, state } from '../lib/state'
import { enqueue } from '../lib/sync'
import { ulid } from '../lib/ulid'

/** Opening float: counted cash in the drawer before the first sale. */
const amount = ref('')

async function open() {
  const shift: LocalShift = {
    id: ulid(),
    opened_at: new Date().toISOString(),
    opened_by: state.staff!.id,
    opening_cash: Number(amount.value || 0),
    cash_movements: [],
  }
  await db.shifts.put(shift)
  await enqueue('shift.open', { id: shift.id, opened_at: shift.opened_at, opened_by: shift.opened_by, opening_cash: shift.opening_cash })
  state.shift = shift
}
</script>

<template>
  <div class="flex min-h-full items-center justify-center p-6">
    <div class="card w-full max-w-sm space-y-4 p-6">
      <h1 class="text-center text-xl font-bold">เปิดกะ</h1>
      <template v-if="can('shift.own') || can('shift.all')">
        <p class="text-center text-slate-600">เงินทอนตั้งต้นในลิ้นชัก</p>
        <div class="input text-right text-3xl font-bold">{{ amount || '0' }}</div>
        <NumPad v-model="amount" decimals />
        <button class="btn-primary w-full" @click="open">เปิดกะ</button>
      </template>
      <p v-else class="text-center text-rose-600">คุณไม่มีสิทธิ์เปิดกะ ให้แคชเชียร์หรือผู้จัดการเปิดก่อน</p>
      <button class="w-full text-slate-500" @click="state.staff = null">ออกจากระบบ</button>
    </div>
  </div>
</template>
