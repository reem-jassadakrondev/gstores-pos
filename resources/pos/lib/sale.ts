import { calculate, type CalcResult } from './calc'
import { itemLabel, resolvePrice, unitOf } from './catalog'
import { db, type Item, type Staff } from './db'
import { settings, state } from './state'
import { enqueue, nextNumber } from './sync'
import { ulid } from './ulid'

export interface CartLine {
  key: string
  item: Item
  unitId: string | null
  qty: number
  unitPrice: number
  discount: number
  /** Price typed by staff instead of the catalog price. */
  priceOverridden?: boolean
}

export interface Payment {
  id: string
  method: 'cash' | 'promptpay' | 'transfer' | 'card' | 'other'
  amount: number
  tendered?: number
  reference?: string
}

export const METHOD_LABELS: Record<string, string> = {
  cash: 'เงินสด',
  promptpay: 'พร้อมเพย์',
  transfer: 'โอนเงิน',
  card: 'บัตร',
  other: 'อื่นๆ',
}

export function taxContext() {
  const entity = state.terminal.legal_entity
  return {
    vat_registered: !!entity.vat_registered,
    prices_include_vat: settings().prices_include_vat !== false,
    vat_rate: Number(entity.vat_rate ?? 7),
    rounding_step: Number(settings().rounding_step ?? 0.01),
  }
}

export function newLine(item: Item, unitId: string | null = null, qty = 1, price: number | null = null): CartLine {
  return {
    key: ulid(),
    item,
    unitId,
    qty,
    unitPrice: price ?? resolvePrice(item, unitId, qty),
    discount: 0,
    priceOverridden: price !== null,
  }
}

/** Re-prices a line after its quantity changed (tier prices), unless staff typed the price. */
export function reprice(line: CartLine): void {
  if (!line.priceOverridden) line.unitPrice = resolvePrice(line.item, line.unitId, line.qty)
}

export function totals(lines: CartLine[], billDiscount: number): CalcResult {
  return calculate({
    lines: lines.map((l) => ({ unit_price: l.unitPrice, qty: l.qty, discount: l.discount, vat_type: l.item.vat_type })),
    bill_discount: billDiscount,
    ...taxContext(),
  })
}

/** Discount above the store limit needs a manager (sales.discount_override). */
export function needsDiscountApproval(result: CalcResult): boolean {
  const max = Number(settings().max_discount_percent ?? 10)
  return result.subtotal > 0 && (result.discount_total / result.subtotal) * 100 > max + 0.001
}

/** IndexedDB can't store Vue's reactive proxies; everything saved goes through this. */
const plain = <T>(value: T): T => JSON.parse(JSON.stringify(value))

const docNumber = (prefix: string, n: number) => `${prefix}-${String(n).padStart(6, '0')}`

/** Records a completed sale locally, queues it for the server and returns what to print. */
export async function completeSale(opts: {
  lines: CartLine[]
  billDiscount: number
  payments: Payment[]
  cashier: Staff
  approvedBy?: Staff | null
  note?: string
}): Promise<any> {
  const result = totals(opts.lines, opts.billDiscount)
  const number = docNumber(state.terminal.terminal.document_prefix, await nextNumber('sale'))
  const data = {
    id: ulid(),
    type: 'sale',
    number,
    shift_id: state.shift!.id,
    channel: 'store',
    cashier_id: opts.cashier.id,
    approved_by: opts.approvedBy?.id ?? null,
    sold_at: new Date().toISOString(),
    bill_discount: result.bill_discount,
    note: opts.note ?? null,
    tax: taxContext(),
    totals: pick(result),
    items: opts.lines.map((l) => ({
      id: ulid(),
      variant_id: l.item.id,
      unit_id: l.unitId,
      name: itemLabel(l.item),
      sku: l.item.sku,
      unit_name: unitOf(l.item, l.unitId)?.name ?? l.item.base_unit,
      qty: l.qty,
      unit_price: l.unitPrice,
      discount: l.discount,
      vat_type: l.item.vat_type,
    })),
    payments: opts.payments,
  }
  const record = plain({ ...data, status: 'completed', cashier_name: opts.cashier.name, change_due: changeDue(opts.payments) })
  await db.sales.put(record)
  await enqueue('sale', stripLocal(record))
  await adjustLocalStock(record.items, -1)

  return record
}

/** Refund (ใบลดหนี้) of lines of an earlier sale. Prices are the original net prices per unit. */
export async function completeCreditNote(opts: {
  original: any
  lines: { item: any; qty: number; restock: boolean }[]
  payments: Payment[]
  cashier: Staff
  approvedBy: Staff
  reason: string
}): Promise<any> {
  const o = opts.original
  const afterLines = o.items.reduce((s: number, i: any) => s + (Number(i.unit_price) * Number(i.qty) - Number(i.discount ?? 0)), 0)
  const billShare = afterLines > 0 ? 1 - Number(o.bill_discount ?? 0) / afterLines : 1
  const items = opts.lines.map(({ item, qty, restock }) => {
    const netPerUnit = ((Number(item.unit_price) * Number(item.qty) - Number(item.discount ?? 0)) * billShare) / Number(item.qty)
    return {
      id: ulid(),
      variant_id: item.variant_id,
      unit_id: item.unit_id ?? null,
      refers_item_id: item.id,
      name: item.name,
      sku: item.sku,
      unit_name: item.unit_name,
      qty,
      unit_price: Math.round(netPerUnit * 100) / 100,
      discount: 0,
      vat_type: item.vat_type,
      restock,
    }
  })
  const tax = { ...(o.tax ?? taxContext()), rounding_step: 0.01 }
  const result = calculate({ lines: items, ...tax })
  const number = `CN-${docNumber(state.terminal.terminal.document_prefix, await nextNumber('credit_note'))}`
  const data = {
    id: ulid(),
    type: 'credit_note',
    number,
    refers_sale_id: o.id,
    shift_id: state.shift!.id,
    channel: 'store',
    cashier_id: opts.cashier.id,
    approved_by: opts.approvedBy.id,
    sold_at: new Date().toISOString(),
    bill_discount: 0,
    note: opts.reason,
    tax,
    totals: pick(result),
    items,
    payments: opts.payments.map((p) => ({ ...p, amount: result.total })).slice(0, 1),
  }
  const record = plain({ ...data, status: 'completed', cashier_name: opts.cashier.name, refers_number: o.number })
  await db.sales.put(record)
  await enqueue('sale', stripLocal(record))
  await adjustLocalStock(record.items.filter((i: any) => i.restock), 1)

  return record
}

/** Void (same shift only — after the Z report a credit note is needed). */
export async function voidSale(sale: any, by: Staff, reason: string): Promise<void> {
  if (sale.shift_id !== state.shift?.id) throw new Error('ยกเลิกได้เฉพาะบิลในกะปัจจุบัน ใช้การคืนเงิน (ใบลดหนี้) แทน')
  const voidedAt = new Date().toISOString()
  await db.sales.update(sale.id, { status: 'voided', voided_at: voidedAt, void_reason: reason, voided_by_name: by.name })
  await enqueue('sale.void', { sale_id: sale.id, voided_at: voidedAt, voided_by: by.id, reason })
  const restock = sale.type === 'sale' ? 1 : -1
  await adjustLocalStock(sale.items.filter((i: any) => sale.type === 'sale' || i.restock), restock)
}

export const changeDue = (payments: Payment[]) =>
  Math.round(payments.reduce((s, p) => s + Math.max(0, (p.tendered ?? p.amount) - p.amount), 0) * 100) / 100

const pick = (r: CalcResult) => ({
  subtotal: r.subtotal,
  discount_total: r.discount_total,
  vatable_amount: r.vatable_amount,
  exempt_amount: r.exempt_amount,
  vat_amount: r.vat_amount,
  rounding: r.rounding,
  total: r.total,
})

/** The server doesn't need display-only fields. */
const stripLocal = ({ status, cashier_name, change_due, refers_number, ...data }: any) => ({
  ...data,
  items: data.items.map(({ unit_name, ...i }: any) => i),
})

/** Keeps the cached on-hand figure roughly right between catalog pulls (server is authoritative). */
async function adjustLocalStock(items: any[], sign: number): Promise<void> {
  for (const i of items) {
    const row = await db.stock.get(i.variant_id)
    const item = await db.items.get(i.variant_id)
    const factor = i.unit_id ? Number(item?.units.find((u) => u.id === i.unit_id)?.factor ?? 1) : 1
    await db.stock.put({ variant_id: i.variant_id, qty: Number(row?.qty ?? 0) + sign * Number(i.qty) * factor })
  }
}

/** X report from the device's own records — same shape as App\Support\Sales\ShiftSummary. */
export async function shiftSummary(shiftId: string): Promise<any> {
  const shift = await db.shifts.get(shiftId)
  const sales = (await db.sales.where('shift_id').equals(shiftId).toArray()).sort((a, b) => a.number.localeCompare(b.number))
  const live = sales.filter((s) => s.status === 'completed')
  const receipts = live.filter((s) => s.type === 'sale')
  const credits = live.filter((s) => s.type === 'credit_note')
  const voided = sales.filter((s) => s.status === 'voided')
  const sum = (rows: any[], f: (s: any) => number) => Math.round(rows.reduce((t, s) => t + f(s), 0) * 100) / 100

  const payments: Record<string, { in: number; out: number; net: number }> = {}
  for (const method of Object.keys(METHOD_LABELS)) {
    const inn = sum(receipts.flatMap((s) => s.payments).filter((p: any) => p.method === method), (p) => Number(p.amount))
    const out = sum(credits.flatMap((s) => s.payments).filter((p: any) => p.method === method), (p) => Number(p.amount))
    if (inn || out) payments[method] = { in: inn, out, net: Math.round((inn - out) * 100) / 100 }
  }
  const moves = shift?.cash_movements ?? []
  const payIn = sum(moves.filter((m) => m.type === 'pay_in'), (m) => m.amount)
  const payOut = sum(moves.filter((m) => m.type === 'pay_out'), (m) => m.amount)
  const opening = Number(shift?.opening_cash ?? 0)
  const cashNet = payments.cash?.net ?? 0
  const numbers = sales.filter((s) => s.type === 'sale').map((s) => s.number)

  return {
    receipts: {
      count: receipts.length,
      total: sum(receipts, (s) => s.totals.total),
      vat: sum(receipts, (s) => s.totals.vat_amount),
      discount: sum(receipts, (s) => s.totals.discount_total),
    },
    credit_notes: { count: credits.length, total: sum(credits, (s) => s.totals.total) },
    voids: { count: voided.length, total: sum(voided, (s) => s.totals.total) },
    net_sales: Math.round((sum(receipts, (s) => s.totals.total) - sum(credits, (s) => s.totals.total)) * 100) / 100,
    net_vat: Math.round((sum(receipts, (s) => s.totals.vat_amount) - sum(credits, (s) => s.totals.vat_amount)) * 100) / 100,
    payments,
    cash: { opening, sales_net: cashNet, pay_in: payIn, pay_out: payOut, expected: Math.round((opening + cashNet + payIn - payOut) * 100) / 100 },
    first_number: numbers[0] ?? null,
    last_number: numbers[numbers.length - 1] ?? null,
  }
}
