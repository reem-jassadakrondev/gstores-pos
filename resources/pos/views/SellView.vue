<script setup lang="ts">
import { computed, onMounted, ref, shallowRef, watch } from 'vue'
import ApprovalDialog from '../components/ApprovalDialog.vue'
import CheckoutDialog from '../components/CheckoutDialog.vue'
import LineDialog from '../components/LineDialog.vue'
import Modal from '../components/Modal.vue'
import NumPad from '../components/NumPad.vue'
import { money } from '../lib/calc'
import { itemLabel, resolveBarcode, unitOf } from '../lib/catalog'
import { db, type Item, type Staff } from '../lib/db'
import { printRows } from '../lib/printer'
import { saleReceipt } from '../lib/receipt'
import { completeSale, needsDiscountApproval, newLine, totals, type CartLine, type Payment } from '../lib/sale'
import { can, settings, state } from '../lib/state'

const items = shallowRef<Item[]>([])
const categories = ref<{ id: string; name: string }[]>([])
const stock = shallowRef<Map<string, number>>(new Map())
const category = ref<string | null>(null)
const search = ref('')
const searchBox = ref<HTMLInputElement | null>(null)
const lines = ref<CartLine[]>([])
const billDiscount = ref(0)
const editing = ref<CartLine | null>(null)
const dialog = ref<null | 'discount' | 'checkout' | 'approve' | 'done'>(null)
const discountInput = ref('')
const message = ref<string | null>(null)
const lastSale = ref<any>(null)
const approvedBy = ref<Staff | null>(null)
let pendingPayments: Payment[] = []

async function load() {
  items.value = (await db.items.toArray()).filter((i) => i.is_active).sort((a, b) => a.product_name.localeCompare(b.product_name, 'th'))
  categories.value = (await db.categories.toArray()).sort((a: any, b: any) => a.sort - b.sort)
  stock.value = new Map((await db.stock.toArray()).map((s) => [s.variant_id, Number(s.qty)]))
}
onMounted(load)
watch(() => state.lastSync, load)

const visible = computed(() => {
  const q = search.value.trim().toLowerCase()
  return items.value
    .filter((i) => (!category.value || i.category_id === category.value) && (!q || i.sku.toLowerCase().includes(q) || itemLabel(i).toLowerCase().includes(q)))
    .slice(0, 80)
})
const result = computed(() => totals(lines.value, billDiscount.value))

function add(item: Item, unitId: string | null = null, qty = 1, price: number | null = null) {
  const same = price === null ? lines.value.find((l) => l.item.id === item.id && l.unitId === unitId && !l.priceOverridden && !item.is_weighable) : null
  if (same) {
    same.qty += qty
    same.unitPrice = newLine(item, unitId, same.qty).unitPrice
  } else {
    lines.value.push(newLine(item, unitId, qty, price))
  }
}

/** Enter in the search box: a barcode (scanner) first, else the only match. */
async function onEnter() {
  const code = search.value.trim()
  if (!code) return
  const hit = await resolveBarcode(code, settings().scale_barcode_mode ?? 'weight')
  if (hit) {
    // Price-embedded scale labels: the label's amount is the line total.
    if (hit.price !== null) add(hit.item, null, 1, hit.price)
    else add(hit.item, hit.unitId, hit.qty ?? 1)
  } else if (visible.value.length === 1) {
    add(visible.value[0])
  } else {
    message.value = `ไม่พบสินค้า "${code}"`
    setTimeout(() => (message.value = null), 2500)
    return
  }
  search.value = ''
}

function applyBillDiscount(percent: boolean) {
  const n = Number(discountInput.value || 0)
  billDiscount.value = percent ? Math.round(result.value.subtotal * n) / 100 : n
  dialog.value = null
  discountInput.value = ''
}

function checkout() {
  approvedBy.value = null
  dialog.value = needsDiscountApproval(result.value) ? 'approve' : 'checkout'
}

async function finish(payments: Payment[]) {
  pendingPayments = payments
  lastSale.value = await completeSale({
    lines: lines.value,
    billDiscount: billDiscount.value,
    payments: pendingPayments,
    cashier: state.staff!,
    approvedBy: approvedBy.value,
  })
  lines.value = []
  billDiscount.value = 0
  dialog.value = 'done'
  printRows(saleReceipt(lastSale.value), { openDrawer: payments.some((p) => p.method === 'cash') }).catch((e) => (message.value = `พิมพ์ไม่สำเร็จ: ${e.message}`))
  void load()
}
</script>

<template>
  <div class="flex h-full gap-3 p-3">
    <!-- Catalog -->
    <section class="flex min-w-0 flex-1 flex-col gap-3">
      <input ref="searchBox" v-model="search" class="input" placeholder="ค้นหาชื่อ / SKU หรือสแกนบาร์โค้ด แล้วกด Enter" autofocus
        @keydown.enter.prevent="onEnter">
      <p v-if="message" class="rounded-xl bg-amber-100 px-4 py-2 text-amber-900">{{ message }}</p>
      <div class="flex gap-2 overflow-x-auto pb-1">
        <button class="btn-ghost shrink-0 py-2" :class="{ '!border-brand !bg-violet-50': !category }" @click="category = null">ทั้งหมด</button>
        <button v-for="c in categories" :key="c.id" class="btn-ghost shrink-0 py-2" :class="{ '!border-brand !bg-violet-50': category === c.id }"
          @click="category = c.id">{{ c.name }}</button>
      </div>
      <div class="grid flex-1 auto-rows-min grid-cols-2 gap-2 overflow-y-auto sm:grid-cols-3 xl:grid-cols-4">
        <button v-for="item in visible" :key="item.id" class="card flex h-28 flex-col justify-between p-3 text-left active:scale-[0.98]" @click="add(item)">
          <span class="line-clamp-2 font-semibold">{{ itemLabel(item) }}</span>
          <span class="flex items-end justify-between">
            <span class="text-lg font-bold text-brand">{{ money(item.price) }}</span>
            <span v-if="item.track_stock" class="text-xs" :class="(stock.get(item.id) ?? 0) <= 0 ? 'text-rose-600' : 'text-slate-400'">
              เหลือ {{ Number((stock.get(item.id) ?? 0).toFixed(3)) }}
            </span>
          </span>
        </button>
        <p v-if="!visible.length" class="col-span-full p-6 text-center text-slate-500">ไม่มีสินค้า (ถ้าเพิ่งจับคู่ รอโหลดแคตตาล็อกสักครู่)</p>
      </div>
    </section>

    <!-- Cart -->
    <aside class="card flex w-[22rem] shrink-0 flex-col">
      <ul class="flex-1 divide-y overflow-y-auto">
        <li v-for="line in lines" :key="line.key" class="cursor-pointer px-4 py-3 hover:bg-slate-50" @click="editing = line">
          <div class="flex justify-between gap-2">
            <span class="font-medium">{{ itemLabel(line.item) }}</span>
            <span class="font-semibold">{{ money(line.unitPrice * line.qty - line.discount) }}</span>
          </div>
          <div class="text-sm text-slate-500">
            {{ Number(line.qty.toFixed(3)) }} {{ unitOf(line.item, line.unitId)?.name ?? line.item.base_unit }} × {{ money(line.unitPrice) }}
            <span v-if="line.discount" class="text-rose-600">ลด {{ money(line.discount) }}</span>
          </div>
        </li>
        <li v-if="!lines.length" class="p-8 text-center text-slate-400">แตะสินค้าหรือสแกนบาร์โค้ดเพื่อเริ่มขาย</li>
      </ul>
      <div class="space-y-1 border-t p-4 text-sm">
        <div class="flex justify-between"><span>รวม</span><span>{{ money(result.subtotal) }}</span></div>
        <div v-if="result.discount_total" class="flex justify-between text-rose-600"><span>ส่วนลด</span><span>-{{ money(result.discount_total) }}</span></div>
        <div v-if="result.vat_amount" class="flex justify-between text-slate-500">
          <span>VAT {{ state.terminal.legal_entity.vat_rate }}% {{ settings().prices_include_vat !== false ? '(รวมในราคา)' : '' }}</span>
          <span>{{ money(result.vat_amount) }}</span>
        </div>
        <div v-if="result.rounding" class="flex justify-between text-slate-500"><span>ปัดเศษ</span><span>{{ money(result.rounding) }}</span></div>
        <div class="flex justify-between pt-1 text-2xl font-bold"><span>สุทธิ</span><span>{{ money(result.total) }}</span></div>
      </div>
      <div class="grid grid-cols-3 gap-2 p-3 pt-0">
        <button class="btn-ghost" :disabled="!lines.length" @click="lines = []; billDiscount = 0">ล้าง</button>
        <button class="btn-ghost" :disabled="!lines.length || !can('sales.discount')" @click="dialog = 'discount'">ส่วนลด</button>
        <button class="btn-primary" :disabled="!lines.length || !can('sales.sell')" @click="checkout">ชำระ</button>
      </div>
    </aside>

    <LineDialog v-if="editing" :line="editing" @close="editing = null"
      @remove="lines.splice(lines.indexOf(editing!), 1); editing = null" />

    <Modal v-if="dialog === 'discount'" title="ส่วนลดท้ายบิล" @close="dialog = null">
      <div class="space-y-3">
        <div class="input text-right text-2xl">{{ discountInput || '0' }}</div>
        <NumPad v-model="discountInput" decimals />
        <div class="grid grid-cols-3 gap-2">
          <button class="btn-ghost" @click="billDiscount = 0; dialog = null">ไม่มีส่วนลด</button>
          <button class="btn-primary" @click="applyBillDiscount(true)">%</button>
          <button class="btn-primary" @click="applyBillDiscount(false)">บาท</button>
        </div>
      </div>
    </Modal>

    <ApprovalDialog v-if="dialog === 'approve'" permission="sales.discount_override" title="ส่วนลดเกินเพดาน ต้องให้ผู้จัดการอนุมัติ"
      @approved="(s) => { approvedBy = s; dialog = 'checkout' }" @close="dialog = null" />

    <CheckoutDialog v-if="dialog === 'checkout'" :total="result.total" @close="dialog = null" @paid="finish" />

    <Modal v-if="dialog === 'done'" title="ขายสำเร็จ" @close="dialog = null; searchBox?.focus()">
      <div class="space-y-4 text-center">
        <p class="text-slate-500">{{ lastSale.number }}</p>
        <p v-if="lastSale.change_due" class="text-lg">เงินทอน</p>
        <p class="text-5xl font-bold text-emerald-600">{{ money(lastSale.change_due || lastSale.totals.total) }}</p>
        <div class="grid grid-cols-2 gap-2">
          <button class="btn-ghost" @click="printRows(saleReceipt(lastSale, true))">พิมพ์อีกใบ</button>
          <button class="btn-primary" @click="dialog = null; searchBox?.focus()">ขายต่อ</button>
        </div>
      </div>
    </Modal>
  </div>
</template>
