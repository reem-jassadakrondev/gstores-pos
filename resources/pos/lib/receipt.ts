import { money } from './calc'
import { METHOD_LABELS } from './sale'
import { settings, state } from './state'

/**
 * A receipt as rows, rendered by both the HTML printer and the ESC/POS printer.
 * Content follows Revenue Department rules for an abbreviated tax invoice
 * (ใบกำกับภาษีอย่างย่อ): seller name + tax ID, branch, POS number, running number, date, VAT.
 */
export type Row =
  | { kind: 'center'; text: string; bold?: boolean; big?: boolean }
  | { kind: 'pair'; left: string; right: string; bold?: boolean }
  | { kind: 'text'; text: string }
  | { kind: 'line' }
  | { kind: 'qr'; data: string }

const fmtDate = (iso: string) =>
  new Date(iso).toLocaleString('th-TH', { timeZone: state.terminal?.store?.timezone ?? 'Asia/Bangkok', dateStyle: 'short', timeStyle: 'short' })

const qtyText = (q: number | string) => String(Number(Number(q).toFixed(3)))

function header(title: string): Row[] {
  const t = state.terminal
  const entity = t.legal_entity
  const branch = t.branch.tax_branch_code === '00000' ? 'สำนักงานใหญ่' : `สาขาที่ ${t.branch.tax_branch_code}`
  const rows: Row[] = [{ kind: 'center', text: t.store.name, bold: true, big: true }]
  if (entity.name && entity.name !== t.store.name) rows.push({ kind: 'center', text: entity.name })
  if (entity.address) rows.push({ kind: 'center', text: entity.address })
  if (entity.tax_id) rows.push({ kind: 'center', text: `เลขประจำตัวผู้เสียภาษี ${entity.tax_id} (${branch})` })
  if (t.terminal.rd_approval_no) rows.push({ kind: 'center', text: `POS# ${t.terminal.rd_approval_no}` })
  if (settings().receipt_header) rows.push({ kind: 'center', text: settings().receipt_header })
  rows.push({ kind: 'line' }, { kind: 'center', text: title, bold: true })
  return rows
}

export function saleReceipt(sale: any, copy = false): Row[] {
  const credit = sale.type === 'credit_note'
  const vat = sale.tax?.vat_registered
  const title = credit ? 'ใบลดหนี้ / ใบรับคืนสินค้า' : vat ? 'ใบเสร็จรับเงิน/ใบกำกับภาษีอย่างย่อ' : 'ใบเสร็จรับเงิน'
  const rows: Row[] = header(title + (copy ? ' (สำเนา)' : ''))
  if (sale.status === 'voided') rows.push({ kind: 'center', text: '*** ยกเลิก ***', bold: true, big: true })
  rows.push(
    { kind: 'pair', left: `เลขที่ ${sale.number}`, right: fmtDate(sale.sold_at) },
    { kind: 'text', text: `พนักงาน: ${sale.cashier_name ?? ''}` },
  )
  if (credit) rows.push({ kind: 'text', text: `อ้างอิงใบเสร็จ ${sale.refers_number ?? ''} · ${sale.note ?? ''}` })
  rows.push({ kind: 'line' })
  for (const item of sale.items) {
    rows.push({ kind: 'text', text: item.name })
    rows.push({
      kind: 'pair',
      left: `  ${qtyText(item.qty)} ${item.unit_name ?? ''} x ${money(item.unit_price)}${Number(item.discount) ? ` -${money(item.discount)}` : ''}`,
      right: money(Number(item.unit_price) * Number(item.qty) - Number(item.discount ?? 0)),
    })
  }
  const t = sale.totals
  rows.push({ kind: 'line' }, { kind: 'pair', left: 'รวม', right: money(t.subtotal) })
  if (t.discount_total) rows.push({ kind: 'pair', left: 'ส่วนลด', right: `-${money(t.discount_total)}` })
  if (vat && !sale.tax.prices_include_vat) rows.push({ kind: 'pair', left: `VAT ${sale.tax.vat_rate}%`, right: money(t.vat_amount) })
  if (t.rounding) rows.push({ kind: 'pair', left: 'ปัดเศษ', right: money(t.rounding) })
  rows.push({ kind: 'pair', left: credit ? 'คืนเงินสุทธิ' : 'ยอดสุทธิ', right: money(t.total), bold: true })
  if (vat) {
    rows.push({ kind: 'text', text: `${sale.tax.prices_include_vat ? 'ราคารวม' : 'ราคาไม่รวม'} VAT ${sale.tax.vat_rate}% · มูลค่าสินค้า ${money(t.vatable_amount)} · VAT ${money(t.vat_amount)}` })
    if (t.exempt_amount) rows.push({ kind: 'text', text: `สินค้ายกเว้น VAT ${money(t.exempt_amount)}` })
  }
  rows.push({ kind: 'line' })
  for (const p of sale.payments) rows.push({ kind: 'pair', left: METHOD_LABELS[p.method] ?? p.method, right: money(p.tendered ?? p.amount) })
  if (sale.change_due) rows.push({ kind: 'pair', left: 'เงินทอน', right: money(sale.change_due), bold: true })
  if (settings().receipt_footer) rows.push({ kind: 'line' }, { kind: 'center', text: settings().receipt_footer })
  return rows
}

export function shiftReport(shift: any, summary: any, z: boolean, staffName: string): Row[] {
  const rows: Row[] = header(z ? `รายงาน Z #${shift.z_number}` : 'รายงาน X (ระหว่างกะ)')
  rows.push(
    { kind: 'pair', left: 'เปิดกะ', right: fmtDate(shift.opened_at) },
    { kind: 'pair', left: z ? 'ปิดกะ' : 'พิมพ์เมื่อ', right: fmtDate(shift.closed_at ?? new Date().toISOString()) },
    { kind: 'text', text: `พนักงาน: ${staffName}` },
    { kind: 'text', text: `เลขที่บิล ${summary.first_number ?? '-'} ถึง ${summary.last_number ?? '-'}` },
    { kind: 'line' },
    { kind: 'pair', left: `บิลขาย (${summary.receipts.count})`, right: money(summary.receipts.total) },
    { kind: 'pair', left: `ใบลดหนี้ (${summary.credit_notes.count})`, right: `-${money(summary.credit_notes.total)}` },
    { kind: 'pair', left: 'ยอดขายสุทธิ', right: money(summary.net_sales), bold: true },
    { kind: 'pair', left: 'VAT สุทธิ', right: money(summary.net_vat) },
    { kind: 'pair', left: 'ส่วนลด', right: money(summary.receipts.discount) },
    { kind: 'pair', left: `บิลยกเลิก (${summary.voids.count})`, right: money(summary.voids.total) },
    { kind: 'line' },
  )
  for (const [method, p] of Object.entries<any>(summary.payments)) rows.push({ kind: 'pair', left: METHOD_LABELS[method] ?? method, right: money(p.net) })
  rows.push(
    { kind: 'line' },
    { kind: 'pair', left: 'เงินทอนตั้งต้น', right: money(summary.cash.opening) },
    { kind: 'pair', left: 'รับเงินสดสุทธิ', right: money(summary.cash.sales_net) },
    { kind: 'pair', left: 'นำเงินเข้า / ออก', right: `${money(summary.cash.pay_in)} / ${money(summary.cash.pay_out)}` },
    { kind: 'pair', left: 'เงินสดที่ควรมี', right: money(summary.cash.expected), bold: true },
  )
  if (z) {
    const diff = Number(shift.counted_cash) - summary.cash.expected
    rows.push({ kind: 'pair', left: 'นับได้', right: money(shift.counted_cash) }, { kind: 'pair', left: 'ขาด / เกิน', right: money(diff), bold: true })
  }
  return rows
}
