<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import ApprovalDialog from '../components/ApprovalDialog.vue'
import Modal from '../components/Modal.vue'
import { api, ApiError } from '../lib/api'
import { money } from '../lib/calc'
import { db, type Staff } from '../lib/db'
import { printRows } from '../lib/printer'
import { saleReceipt } from '../lib/receipt'
import { completeCreditNote, METHOD_LABELS, voidSale } from '../lib/sale'
import { state } from '../lib/state'
import { ulid } from '../lib/ulid'

/** Bills on this device: reprint, void (same shift) or refund with a credit note. */
const sales = ref<any[]>([])
const onlyShift = ref(true)
const search = ref('')
const selected = ref<any>(null)
const step = ref<null | 'void-approve' | 'void-reason' | 'refund-approve' | 'refund'>(null)
const approver = ref<Staff | null>(null)
const reason = ref('')
const error = ref<string | null>(null)
const refundLines = ref<{ item: any; qty: number; max: number; restock: boolean }[]>([])
const refundMethod = ref<'cash' | 'promptpay' | 'transfer' | 'card'>('cash')

async function load() {
  const all = await db.sales.orderBy('sold_at').reverse().limit(500).toArray()
  sales.value = onlyShift.value ? all.filter((s) => s.shift_id === state.shift?.id) : all
}
onMounted(load)

const shown = computed(() => sales.value.filter((s) => !search.value || s.number.includes(search.value.trim())))

/** Receipts from another terminal are fetched from the server (needs network). */
async function lookup() {
  error.value = null
  try {
    const remote = await api(`sales/${encodeURIComponent(search.value.trim())}`)
    selected.value = { ...remote, totals: { total: remote.total }, payments: [], remote: true, tax: { vat_registered: remote.vat_registered, prices_include_vat: remote.prices_include_vat, vat_rate: Number(remote.vat_rate) } }
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'ค้นหาใบเสร็จจากเครื่องอื่นต้องใช้อินเทอร์เน็ต'
  }
}

async function doVoid() {
  try {
    await voidSale(selected.value, approver.value!, reason.value)
    await printRows(saleReceipt({ ...selected.value, status: 'voided' }, true)).catch(() => {})
    step.value = null
    selected.value = null
    reason.value = ''
    await load()
  } catch (e: any) {
    error.value = e.message
  }
}

async function startRefund(staff: Staff) {
  approver.value = staff
  const credited = await db.sales.filter((s) => s.type === 'credit_note').toArray()
  refundLines.value = selected.value.items.map((item: any) => {
    const done = item.refunded_qty ?? credited.filter((c: any) => c.status === 'completed')
      .flatMap((c: any) => c.items).filter((i: any) => i.refers_item_id === item.id).reduce((s: number, i: any) => s + Number(i.qty), 0)
    const max = Math.max(0, Number(item.qty) - done)
    return { item, qty: 0, max, restock: true }
  })
  step.value = 'refund'
}

async function doRefund() {
  const lines = refundLines.value.filter((l) => l.qty > 0)
  if (!lines.length || !reason.value) return
  const note = await completeCreditNote({
    original: selected.value,
    lines,
    payments: [{ id: ulid(), method: refundMethod.value, amount: 0 }],
    cashier: state.staff!,
    approvedBy: approver.value!,
    reason: reason.value,
  })
  await printRows(saleReceipt(note), { openDrawer: refundMethod.value === 'cash' }).catch(() => {})
  step.value = null
  selected.value = null
  reason.value = ''
  await load()
}
</script>

<template>
  <div class="flex h-full gap-3 p-3">
    <section class="card flex w-[26rem] shrink-0 flex-col">
      <div class="space-y-2 border-b p-3">
        <div class="flex gap-2">
          <input v-model="search" class="input" placeholder="เลขที่ใบเสร็จ">
          <button class="btn-ghost shrink-0" :disabled="!search" title="ค้นจากเซิร์ฟเวอร์" @click="lookup">ค้นออนไลน์</button>
        </div>
        <label class="flex items-center gap-2 text-sm"><input v-model="onlyShift" type="checkbox" @change="load"> เฉพาะกะนี้</label>
        <p v-if="error" class="text-sm text-rose-600">{{ error }}</p>
      </div>
      <ul class="flex-1 divide-y overflow-y-auto">
        <li v-for="s in shown" :key="s.id" class="cursor-pointer px-4 py-3 hover:bg-slate-50" :class="{ 'bg-violet-50': selected?.id === s.id }"
          @click="selected = s; error = null">
          <div class="flex justify-between">
            <span class="font-mono text-sm">{{ s.number }}</span>
            <span class="font-semibold" :class="{ 'line-through text-slate-400': s.status === 'voided' }">
              {{ s.type === 'credit_note' ? '-' : '' }}{{ money(s.totals.total) }}
            </span>
          </div>
          <div class="text-xs text-slate-500">
            {{ new Date(s.sold_at).toLocaleTimeString('th-TH') }} · {{ s.cashier_name }}
            <b v-if="s.status === 'voided'" class="text-rose-600">ยกเลิก</b>
            <b v-if="s.type === 'credit_note'" class="text-amber-600">ใบลดหนี้</b>
          </div>
        </li>
      </ul>
    </section>

    <section v-if="selected" class="card flex-1 space-y-3 overflow-y-auto p-5">
      <h2 class="text-xl font-bold">{{ selected.number }}</h2>
      <ul class="divide-y">
        <li v-for="i in selected.items" :key="i.id" class="flex justify-between py-2">
          <span>{{ i.name }} × {{ Number(i.qty) }}</span><span>{{ money(Number(i.unit_price) * Number(i.qty) - Number(i.discount ?? 0)) }}</span>
        </li>
      </ul>
      <p class="text-right text-2xl font-bold">{{ money(selected.totals.total) }}</p>
      <p v-for="p in selected.payments" :key="p.id" class="text-right text-sm text-slate-500">{{ METHOD_LABELS[p.method] }} {{ money(p.amount) }}</p>
      <p v-if="selected.status === 'voided'" class="text-rose-600">ยกเลิกแล้ว: {{ selected.void_reason }}</p>
      <div class="flex flex-wrap gap-2">
        <button v-if="!selected.remote" class="btn-ghost" @click="printRows(saleReceipt(selected, true))">พิมพ์สำเนา</button>
        <button v-if="selected.status === 'completed' && selected.shift_id === state.shift?.id" class="btn-danger" @click="step = 'void-approve'">ยกเลิกบิล</button>
        <button v-if="selected.status === 'completed' && selected.type === 'sale'" class="btn-ghost" @click="step = 'refund-approve'">คืนสินค้า / คืนเงิน</button>
      </div>
    </section>

    <ApprovalDialog v-if="step === 'void-approve'" permission="sales.void" title="อนุมัติยกเลิกบิล"
      @approved="(s) => { approver = s; step = 'void-reason' }" @close="step = null" />
    <Modal v-if="step === 'void-reason'" title="เหตุผลที่ยกเลิก" @close="step = null">
      <div class="space-y-3">
        <input v-model="reason" class="input" placeholder="เช่น คีย์ผิด, ลูกค้าเปลี่ยนใจ" autofocus>
        <button class="btn-danger w-full" :disabled="!reason" @click="doVoid">ยืนยันยกเลิกบิล</button>
      </div>
    </Modal>

    <ApprovalDialog v-if="step === 'refund-approve'" permission="sales.refund" title="อนุมัติคืนเงิน" @approved="startRefund" @close="step = null" />
    <Modal v-if="step === 'refund'" title="คืนสินค้า (ออกใบลดหนี้)" wide @close="step = null">
      <div class="space-y-3">
        <div v-for="l in refundLines" :key="l.item.id" class="flex items-center gap-3">
          <span class="flex-1">{{ l.item.name }} <small class="text-slate-500">(คืนได้ {{ l.max }})</small></span>
          <input v-model.number="l.qty" type="number" min="0" :max="l.max" class="input w-24 py-2">
          <label class="flex items-center gap-1 text-sm"><input v-model="l.restock" type="checkbox"> คืนเข้าสต็อก</label>
        </div>
        <div class="grid grid-cols-4 gap-2">
          <button v-for="m in (['cash', 'promptpay', 'transfer', 'card'] as const)" :key="m" class="btn-ghost text-sm"
            :class="{ '!border-brand !bg-violet-50': refundMethod === m }" @click="refundMethod = m">{{ METHOD_LABELS[m] }}</button>
        </div>
        <input v-model="reason" class="input" placeholder="เหตุผล เช่น สินค้าชำรุด">
        <button class="btn-primary w-full" :disabled="!reason || !refundLines.some((l) => l.qty > 0 && l.qty <= l.max)" @click="doRefund">ออกใบลดหนี้และคืนเงิน</button>
      </div>
    </Modal>
  </div>
</template>
