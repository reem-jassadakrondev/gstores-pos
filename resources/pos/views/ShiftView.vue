<script setup lang="ts">
import { onMounted, ref } from 'vue'
import Modal from '../components/Modal.vue'
import NumPad from '../components/NumPad.vue'
import { money } from '../lib/calc'
import { db } from '../lib/db'
import { printRows } from '../lib/printer'
import { shiftReport } from '../lib/receipt'
import { METHOD_LABELS, shiftSummary } from '../lib/sale'
import { can, state } from '../lib/state'
import { enqueue, nextNumber } from '../lib/sync'
import { ulid } from '../lib/ulid'

/** X report, cash in/out and closing the shift with a Z report. */
const summary = ref<any>(null)
const dialog = ref<null | 'move' | 'close'>(null)
const moveType = ref<'pay_in' | 'pay_out'>('pay_out')
const amount = ref('')
const reason = ref('')
const counted = ref('')

async function load() {
  summary.value = await shiftSummary(state.shift!.id)
}
onMounted(load)

async function saveMove() {
  const move = { id: ulid(), shift_id: state.shift!.id, type: moveType.value, amount: Number(amount.value), reason: reason.value, user_id: state.staff!.id, occurred_at: new Date().toISOString() }
  const shift = (await db.shifts.get(state.shift!.id))!
  shift.cash_movements.push(JSON.parse(JSON.stringify(move)))
  await db.shifts.put(shift)
  state.shift = shift
  await enqueue('cash.move', move)
  dialog.value = null
  amount.value = ''
  reason.value = ''
  await load()
}

async function closeShift() {
  const shift = (await db.shifts.get(state.shift!.id))!
  shift.closed_at = new Date().toISOString()
  shift.counted_cash = Number(counted.value || 0)
  shift.z_number = await nextNumber('z')
  await db.shifts.put(shift)
  await enqueue('shift.close', { id: shift.id, closed_at: shift.closed_at, closed_by: state.staff!.id, counted_cash: shift.counted_cash, z_number: shift.z_number })
  const z = await shiftSummary(shift.id)
  await printRows(shiftReport(shift, z, true, state.staff!.name)).catch(() => {})
  state.shift = null
  state.staff = null
}
</script>

<template>
  <div v-if="summary" class="mx-auto max-w-3xl space-y-3 p-3">
    <div class="card grid grid-cols-2 gap-4 p-5 sm:grid-cols-4">
      <div><small class="text-slate-500">บิลขาย</small><p class="text-2xl font-bold">{{ summary.receipts.count }}</p></div>
      <div><small class="text-slate-500">ยอดขายสุทธิ</small><p class="text-2xl font-bold">{{ money(summary.net_sales) }}</p></div>
      <div><small class="text-slate-500">ใบลดหนี้</small><p class="text-2xl font-bold">{{ summary.credit_notes.count }}</p></div>
      <div><small class="text-slate-500">บิลยกเลิก</small><p class="text-2xl font-bold">{{ summary.voids.count }}</p></div>
    </div>
    <div class="card divide-y p-5">
      <div v-for="(p, m) in summary.payments" :key="m" class="flex justify-between py-2"><span>{{ METHOD_LABELS[m] }}</span><span>{{ money(p.net) }}</span></div>
      <div class="flex justify-between py-2"><span>เงินทอนตั้งต้น</span><span>{{ money(summary.cash.opening) }}</span></div>
      <div class="flex justify-between py-2"><span>นำเงินเข้า / ออก</span><span>{{ money(summary.cash.pay_in) }} / {{ money(summary.cash.pay_out) }}</span></div>
      <div class="flex justify-between py-2 text-lg font-bold"><span>เงินสดที่ควรมีในลิ้นชัก</span><span>{{ money(summary.cash.expected) }}</span></div>
    </div>
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
      <button class="btn-ghost" @click="printRows(shiftReport(state.shift, summary, false, state.staff!.name))">พิมพ์ X</button>
      <button class="btn-ghost" :disabled="!can('shift.own') && !can('shift.all')" @click="moveType = 'pay_in'; dialog = 'move'">นำเงินเข้า</button>
      <button class="btn-ghost" :disabled="!can('shift.own') && !can('shift.all')" @click="moveType = 'pay_out'; dialog = 'move'">นำเงินออก</button>
      <button class="btn-danger" :disabled="!can('shift.own') && !can('shift.all')" @click="dialog = 'close'">ปิดกะ (Z)</button>
    </div>

    <Modal v-if="dialog === 'move'" :title="moveType === 'pay_in' ? 'นำเงินเข้าลิ้นชัก' : 'นำเงินออกจากลิ้นชัก'" @close="dialog = null">
      <div class="space-y-3">
        <div class="input text-right text-2xl">{{ amount || '0' }}</div>
        <NumPad v-model="amount" decimals />
        <input v-model="reason" class="input" placeholder="เหตุผล เช่น ซื้อน้ำแข็ง, แลกเหรียญ">
        <button class="btn-primary w-full" :disabled="!(Number(amount) > 0) || !reason" @click="saveMove">บันทึก</button>
      </div>
    </Modal>

    <Modal v-if="dialog === 'close'" title="ปิดกะ: นับเงินสดในลิ้นชัก" @close="dialog = null">
      <div class="space-y-3">
        <div class="input text-right text-3xl font-bold">{{ counted || '0' }}</div>
        <NumPad v-model="counted" decimals />
        <p v-if="counted" class="text-center" :class="Number(counted) - summary.cash.expected < 0 ? 'text-rose-600' : 'text-emerald-600'">
          ขาด/เกิน {{ money(Number(counted) - summary.cash.expected) }}
        </p>
        <button class="btn-danger w-full" @click="closeShift">ยืนยันปิดกะและพิมพ์รายงาน Z</button>
      </div>
    </Modal>
  </div>
</template>
