<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { db, setMeta } from '../lib/db'
import { connectPrinter, defaultPrinter, printerConfig, printRows, savePrinterConfig, type PrinterConfig } from '../lib/printer'
import { settings, state } from '../lib/state'
import { pull, pushOutbox, retryFailed } from '../lib/sync'

/** Device setup and sync health. */
const printer = ref<PrinterConfig>(defaultPrinter())
const failed = ref<any[]>([])
const message = ref<string | null>(null)

async function load() {
  printer.value = await printerConfig()
  if (!(await db.meta.get('printer'))) printer.value.paperWidth = Number(settings().paper_width ?? 80) as 58 | 80
  failed.value = await db.outbox.where('status').equals('failed').toArray()
}
onMounted(load)

async function save() {
  await savePrinterConfig({ ...printer.value })
  message.value = 'บันทึกแล้ว'
}

async function connect() {
  try {
    await connectPrinter(printer.value.mode)
    await save()
  } catch (e: any) {
    message.value = `เชื่อมต่อไม่สำเร็จ: ${e.message}`
  }
}

async function test() {
  await save()
  await printRows([
    { kind: 'center', text: 'ทดสอบเครื่องพิมพ์', bold: true, big: true },
    { kind: 'text', text: 'ภาษาไทย ก ข ค ง ที่นี่ น้ำ ปั๊ม' },
    { kind: 'pair', left: 'ยอดรวม', right: '1,234.50' },
    { kind: 'line' },
  ], { openDrawer: true }).catch((e) => (message.value = `พิมพ์ไม่สำเร็จ: ${e.message}`))
}

async function unpair() {
  if (state.pending + state.failed > 0) {
    message.value = 'ยังมีข้อมูลที่ยังไม่ได้ส่งขึ้นเซิร์ฟเวอร์ ส่งให้หมดก่อนจึงตัดการเชื่อมต่อได้'
    return
  }
  if (!confirm('ตัดการเชื่อมต่อเครื่องนี้? ต้องใช้รหัสจับคู่ใหม่')) return
  await setMeta('token', null)
  state.paired = false
  state.staff = null
}
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-3 p-3">
    <p v-if="message" class="rounded-xl bg-violet-100 px-4 py-2">{{ message }}</p>
    <div class="card space-y-1 p-5">
      <h2 class="text-lg font-bold">เครื่องนี้</h2>
      <p>{{ state.terminal.store.name }} · {{ state.terminal.branch.name }} · {{ state.terminal.terminal.name }} ({{ state.terminal.terminal.document_prefix }})</p>
      <p class="text-sm text-slate-500">เลขอนุมัติ ภ.พ.06: {{ state.terminal.terminal.rd_approval_no ?? 'ยังไม่ได้ระบุ' }}</p>
    </div>

    <div class="card space-y-3 p-5">
      <h2 class="text-lg font-bold">การซิงก์</h2>
      <p>{{ state.online ? 'ออนไลน์' : 'ออฟไลน์' }} · รอส่ง {{ state.pending }} · ผิดพลาด {{ state.failed }} · ล่าสุด {{ state.lastSync ? new Date(state.lastSync).toLocaleString('th-TH') : '-' }}</p>
      <ul v-if="failed.length" class="space-y-1 text-sm text-rose-700">
        <li v-for="f in failed" :key="f.seq">{{ f.op }} {{ f.data.number ?? f.data.id ?? f.data.sale_id }}: {{ f.error }}</li>
      </ul>
      <div class="flex flex-wrap gap-2">
        <button class="btn-ghost" @click="pushOutbox().then(() => pull()).then(load)">ซิงก์ตอนนี้</button>
        <button class="btn-ghost" @click="pull(true)">โหลดแคตตาล็อกใหม่ทั้งหมด</button>
        <button v-if="failed.length" class="btn-ghost" @click="retryFailed().then(load)">ส่งรายการที่ผิดพลาดอีกครั้ง</button>
      </div>
    </div>

    <div class="card space-y-3 p-5">
      <h2 class="text-lg font-bold">เครื่องพิมพ์ใบเสร็จ</h2>
      <select v-model="printer.mode" class="input">
        <option value="browser">พิมพ์ผ่านระบบ (ทุกอุปกรณ์ · Sunmi · AirPrint · ไดรเวอร์ PC)</option>
        <option value="usb">ESC/POS ผ่าน USB (Chrome/Edge)</option>
        <option value="serial">ESC/POS ผ่าน Serial/COM (Chrome/Edge บน PC)</option>
        <option value="bluetooth">ESC/POS ผ่าน Bluetooth (Chrome บน Android)</option>
        <option value="none">ไม่พิมพ์</option>
      </select>
      <div class="grid grid-cols-2 gap-2">
        <label>กระดาษ
          <select v-model.number="printer.paperWidth" class="input"><option :value="58">58 มม.</option><option :value="80">80 มม.</option></select>
        </label>
        <label v-if="printer.mode !== 'browser'">Code page ภาษาไทย
          <input v-model.number="printer.codePage" type="number" class="input">
        </label>
      </div>
      <label class="flex items-center gap-2"><input v-model="printer.openDrawer" type="checkbox"> เปิดลิ้นชักเงินเมื่อรับเงินสด (ESC/POS)</label>
      <div class="flex flex-wrap gap-2">
        <button v-if="['usb', 'serial', 'bluetooth'].includes(printer.mode)" class="btn-ghost" @click="connect">เลือกเครื่องพิมพ์</button>
        <button class="btn-ghost" @click="test">พิมพ์ทดสอบ</button>
        <button class="btn-primary" @click="save">บันทึก</button>
      </div>
    </div>

    <button class="btn-danger" @click="unpair">ตัดการเชื่อมต่อเครื่องนี้</button>
  </div>
</template>
