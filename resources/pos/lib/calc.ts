/**
 * Bill arithmetic — a line-for-line copy of App\Support\Sales\SaleCalculator.
 * Both are checked against tests/fixtures/sale-calc.json. Change them together.
 */

export interface CalcLine {
  unit_price: number | string
  qty: number | string
  discount?: number | string
  vat_type?: string
}

export interface CalcInput {
  lines: CalcLine[]
  bill_discount?: number | string
  vat_registered?: boolean
  vat_rate?: number | string
  prices_include_vat?: boolean
  rounding_step?: number | string
}

export interface CalcResult {
  lines: { gross: number; discount: number; line_total: number }[]
  subtotal: number
  line_discount_total: number
  bill_discount: number
  discount_total: number
  vatable_amount: number
  exempt_amount: number
  vat_amount: number
  rounding: number
  total: number
}

/** Round half up for non-negative numbers (same as the PHP floor($x + 0.5)). */
const r = (x: number): number => Math.floor(x + 0.5)

export function calculate(input: CalcInput): CalcResult {
  const registered = Boolean(input.vat_registered ?? false)
  const rate = Number(input.vat_rate ?? 0)
  const inclusive = Boolean(input.prices_include_vat ?? true)

  const lines = input.lines.map((line) => {
    const price = r(Number(line.unit_price) * 100)
    const qty = r(Number(line.qty) * 1000)
    const gross = r((price * qty) / 1000)
    const discount = Math.min(r(Number(line.discount ?? 0) * 100), gross)
    return { gross, discount, net: gross - discount, vatable: registered && (line.vat_type ?? 'vat') === 'vat' }
  })

  const subtotal = lines.reduce((s, l) => s + l.gross, 0)
  const afterLines = lines.reduce((s, l) => s + l.net, 0)
  const billDiscount = Math.min(r(Number(input.bill_discount ?? 0) * 100), afterLines)

  // Largest-remainder allocation of the bill discount.
  const alloc = lines.map(() => 0)
  if (billDiscount > 0 && afterLines > 0) {
    const fractions: number[] = []
    lines.forEach((line, i) => {
      const share = (billDiscount * line.net) / afterLines
      alloc[i] = Math.floor(share)
      fractions[i] = share - alloc[i]
    })
    let left = billDiscount - alloc.reduce((s, a) => s + a, 0)
    const order = lines.map((_, i) => i).sort((a, b) => fractions[b] - fractions[a] || a - b)
    for (let k = 0; k < left; k++) alloc[order[k]]++
  }

  let vatableBase = 0
  let exempt = 0
  lines.forEach((line, i) => {
    const after = line.net - alloc[i]
    if (line.vatable) vatableBase += after
    else exempt += after
  })

  const base = afterLines - billDiscount
  let vat: number
  let vatable: number
  let total: number
  if (!registered || rate <= 0) {
    vat = 0
    vatable = vatableBase
    total = base
  } else if (inclusive) {
    vat = r((vatableBase * rate) / (100 + rate))
    vatable = vatableBase - vat
    total = base
  } else {
    vat = r((vatableBase * rate) / 100)
    vatable = vatableBase
    total = base + vat
  }

  const step = Math.max(1, r(Number(input.rounding_step ?? 0.01) * 100))
  const rounded = r(total / step) * step

  return {
    lines: lines.map((l) => ({ gross: l.gross / 100, discount: l.discount / 100, line_total: l.net / 100 })),
    subtotal: subtotal / 100,
    line_discount_total: (subtotal - afterLines) / 100,
    bill_discount: billDiscount / 100,
    discount_total: (subtotal - afterLines + billDiscount) / 100,
    vatable_amount: vatable / 100,
    exempt_amount: exempt / 100,
    vat_amount: vat / 100,
    rounding: (rounded - total) / 100,
    total: rounded / 100,
  }
}

/** Formats baht for screens and receipts: 1234.5 → "1,234.50". */
export function money(value: number | string): string {
  return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}
