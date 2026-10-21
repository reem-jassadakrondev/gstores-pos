<script setup lang="ts">
import QRCode from 'qrcode'
import { computed, ref, watch } from 'vue'
import { money } from '../lib/calc'
import { promptPayPayload } from '../lib/promptpay'
import { METHOD_LABELS, type Payment } from '../lib/sale'
import { settings } from '../lib/state'
import { ulid } from '../lib/ulid'
import Modal from './Modal.vue'
import NumPad from './NumPad.vue'

/** Take payment: any mix of methods until the total is covered. Cash may exceed it (change). */
const props = defineProps<{ total: number }>()
const emit = defineEmits<{ close: []; paid: [payments: Payment[]] }>()

const payments = ref<Payment[]>([])
const method = ref<Payment['method']>('cash')
const amount = ref('')
const reference = ref('')
const qr = ref<string | null>(null)

const paid = computed(() => payments.value.reduce((s, p) => s + p.amount, 0))
const remaining = computed(() => Math.round((props.total - paid.value) * 100) / 100)
const quick = computed(() => [remaining.value, ...[20, 50, 100, 500, 1000].filter((n) => n > remaining.value)].slice(0, 5))

watch([method, remaining], async () => {
  qr.value = null
  if (method.value === 'promptpay' && settings().promptpay_id && remaining.value > 0) {
    qr.value = await QRCode.toDataURL(promptPayPayload(settings().promptpay_id, remaining.value), { width: 260, margin: 1 })
  }
}, { immediate: true })

function add(value?: number) {
  const given = value ?? Number(amount.value || remaining.value)
  if (!(given > 0) || remaining.value <= 0) return
  const applied = Math.min(given, remaining.value)
  if (method.value !== 'cash' && given > remaining.value + 0.001) return // only cash gives change
  payments.value.push({
    id: ulid(),
    method: method.value,
    amount: Math.round(applied * 100) / 100,
    ...(method.value === 'cash' ? { tendered: given } : {}),
    ...(reference.value ? { reference: reference.value } : {}),
  })
  amount.value = ''
  reference.value = ''
  if (remaining.value <= 0.001) emit('paid', payments.value)
}
</script>

<template>
  <Modal title="ชำระเงิน" wide @close="$emit('close')">
    <div class="grid gap-5 md:grid-cols-2">
      <div class="space-y-3">
        <div class="rounded-2xl bg-slate-900 p-4 text-right text-white">
          <div class="text-sm opacity-70">ยอดที่ต้องชำระ</div>
          <div class="text-4xl font-bold">{{ money(remaining) }}</div>
          <div v-if="paid" class="text-sm opacity-70">ชำระแล้ว {{ money(paid) }} จาก {{ money(total) }}</div>
        </div>
        <div class="grid grid-cols-4 gap-2">
          <button v-for="m in (['cash', 'promptpay', 'transfer', 'card'] as const)" :key="m" class="btn-ghost px-2 text-sm"
            :class="{ '!border-brand !bg-violet-50': method === m }" @click="method = m">{{ METHOD_LABELS[m] }}</button>
        </div>
        <ul v-if="payments.length" class="space-y-1 text-sm">
          <li v-for="p in payments" :key="p.id" class="flex justify-between">
            <span>{{ METHOD_LABELS[p.method] }} {{ p.reference ?? '' }}</span><span>{{ money(p.tendered ?? p.amount) }}</span>
          </li>
        </ul>
      </div>

      <div class="space-y-3">
        <template v-if="method === 'cash'">
          <div class="grid grid-cols-3 gap-2">
            <button v-for="q in quick" :key="q" class="btn-ghost" @click="add(q)">{{ q === remaining ? 'พอดี' : money(q) }}</button>
          </div>
          <div class="input text-right text-2xl">{{ amount || money(remaining) }}</div>
          <NumPad v-model="amount" decimals />
          <button class="btn-primary w-full" @click="add()">รับเงินสด</button>
        </template>
        <template v-else-if="method === 'promptpay'">
          <p v-if="!settings().promptpay_id" class="text-rose-600">ยังไม่ได้ตั้งพร้อมเพย์ของร้าน (หลังบ้าน → ร้าน → ตั้งค่าหน้าขาย)</p>
          <div v-else class="space-y-2 text-center">
            <img v-if="qr" :src="qr" alt="PromptPay QR" class="mx-auto rounded-xl border">
            <p class="text-sm text-slate-500">ให้ลูกค้าสแกนแล้วตรวจยอดเงินเข้าก่อนกดยืนยัน</p>
            <input v-model="reference" class="input" placeholder="เลขอ้างอิง / 4 ตัวท้ายสลิป (ถ้ามี)">
            <button class="btn-primary w-full" @click="add(remaining)">ได้รับเงินแล้ว {{ money(remaining) }}</button>
          </div>
        </template>
        <template v-else>
          <input v-model="reference" class="input" :placeholder="method === 'card' ? 'เลขอนุมัติจากเครื่อง EDC' : 'เลขอ้างอิงการโอน'">
          <div class="input text-right text-2xl">{{ amount || money(remaining) }}</div>
          <NumPad v-model="amount" decimals />
          <button class="btn-primary w-full" @click="add()">ยืนยัน {{ METHOD_LABELS[method] }}</button>
        </template>
      </div>
    </div>
  </Modal>
</template>
