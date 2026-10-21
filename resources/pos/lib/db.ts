import Dexie, { type Table } from 'dexie'

/** Everything the till needs to keep selling without a network lives here (IndexedDB). */

export interface Price {
  unit_id: string | null
  channel: string
  branch_specific: boolean
  min_qty: string | number
  price: string | number
}

export interface Unit {
  id: string
  name: string
  factor: string | number
}

/** A sellable SKU, flattened with its product so the sell screen needs one lookup. */
export interface Item {
  id: string
  product_id: string
  category_id: string | null
  product_name: string
  name: string
  sku: string
  price: string | number
  is_active: boolean
  base_unit: string
  vat_type: string
  track_stock: boolean
  is_weighable: boolean
  plu: string | null
  units: Unit[]
  prices: Price[]
}

export interface Staff {
  id: number
  name: string
  org_role: string
  permissions: string[]
  pin: { salt: string; hash: string; iterations: number } | null
}

export interface OutboxOp {
  seq?: number
  op: string
  data: any
  status: 'pending' | 'failed'
  error?: string
  created_at: string
}

export interface LocalShift {
  id: string
  opened_at: string
  opened_by: number
  opening_cash: number
  closed_at?: string
  z_number?: number
  counted_cash?: number
  cash_movements: { id: string; type: 'pay_in' | 'pay_out'; amount: number; reason: string; user_id: number; occurred_at: string }[]
}

class PosDb extends Dexie {
  meta!: Table<{ key: string; value: any }, string>
  categories!: Table<{ id: string; parent_id: string | null; name: string; sort: number }, string>
  items!: Table<Item, string>
  barcodes!: Table<{ code: string; variant_id: string; unit_id: string | null; product_id: string }, string>
  stock!: Table<{ variant_id: string; qty: string | number }, string>
  staff!: Table<Staff, number>
  outbox!: Table<OutboxOp, number>
  sales!: Table<any, string>
  shifts!: Table<LocalShift, string>

  constructor(name = 'reemo-pos') {
    super(name)
    this.version(1).stores({
      meta: 'key',
      categories: 'id',
      items: 'id, product_id, sku, plu',
      barcodes: 'code, product_id',
      stock: 'variant_id',
      staff: 'id',
      outbox: '++seq, status',
      sales: 'id, number, shift_id, sold_at',
      shifts: 'id',
    })
  }
}

export const db = new PosDb()

export async function getMeta<T = any>(key: string, fallback: T | null = null): Promise<T | null> {
  return ((await db.meta.get(key))?.value as T) ?? fallback
}

export async function setMeta(key: string, value: any): Promise<void> {
  await db.meta.put({ key, value })
}
