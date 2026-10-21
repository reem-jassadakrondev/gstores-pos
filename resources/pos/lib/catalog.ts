import { db, type Item, type Unit } from './db'

/** Applies a /catalog response: a full download replaces everything, a delta replaces changed products. */
export async function applyCatalog(payload: any): Promise<void> {
  await db.transaction('rw', [db.categories, db.items, db.barcodes, db.stock], async () => {
    if (payload.full) {
      await Promise.all([db.categories.clear(), db.items.clear(), db.barcodes.clear(), db.stock.clear()])
    }
    await db.categories.bulkPut(payload.categories)
    await db.categories.bulkDelete(payload.deleted?.categories ?? [])

    const changed: string[] = payload.products.map((p: any) => p.id)
    const gone: string[] = [...changed, ...(payload.deleted?.products ?? [])]
    await db.items.where('product_id').anyOf(gone).delete()
    await db.barcodes.where('product_id').anyOf(gone).delete()
    await db.items.bulkDelete(payload.deleted?.variants ?? [])

    const items: Item[] = []
    const barcodes: any[] = []
    for (const p of payload.products) {
      for (const v of p.variants) {
        items.push({
          id: v.id,
          product_id: p.id,
          category_id: p.category_id,
          product_name: p.name,
          name: v.name,
          sku: v.sku,
          price: v.price,
          is_active: p.is_active && v.is_active,
          base_unit: p.base_unit,
          vat_type: p.vat_type,
          track_stock: p.track_stock,
          is_weighable: p.is_weighable,
          plu: p.plu,
          units: p.units,
          prices: v.prices,
        })
        for (const b of v.barcodes) barcodes.push({ code: b.code, variant_id: v.id, unit_id: b.unit_id, product_id: p.id })
      }
    }
    await db.items.bulkPut(items)
    await db.barcodes.bulkPut(barcodes)
    await db.stock.bulkPut(payload.stock)
  })
}

export const itemLabel = (item: Item) => (item.name ? `${item.product_name} · ${item.name}` : item.product_name)

export const unitOf = (item: Item, unitId: string | null): Unit | null => item.units.find((u) => u.id === unitId) ?? null

/**
 * Same rules as App\Support\Catalog\PriceResolver (channel "store" at this branch):
 * branch price > all-branch price; highest min_qty not above qty; else price × unit factor.
 */
export function resolvePrice(item: Item, unitId: string | null, qty = 1, channel = 'store'): number {
  const channels = channel === 'store' ? ['store'] : [channel, 'store']
  for (const c of channels) {
    const match = item.prices
      .filter((p) => p.channel === c && p.unit_id === unitId && Number(p.min_qty) <= qty + 0.0005)
      .sort((a, b) => Number(b.branch_specific) - Number(a.branch_specific) || Number(b.min_qty) - Number(a.min_qty))[0]
    if (match) return Number(match.price)
  }
  const factor = unitId ? Number(unitOf(item, unitId)?.factor ?? 1) : 1
  return Math.round(Number(item.price) * factor * 100) / 100
}

export function validEan13(code: string): boolean {
  if (!/^\d{13}$/.test(code)) return false
  let sum = 0
  for (let i = 0; i < 12; i++) sum += Number(code[i]) * (i % 2 ? 3 : 1)
  return (10 - (sum % 10)) % 10 === Number(code[12])
}

/** Same as App\Support\Catalog\BarcodeResolver, against the local cache. */
export async function resolveBarcode(
  code: string,
  scaleMode: string,
): Promise<{ item: Item; unitId: string | null; qty: number | null; price: number | null } | null> {
  code = code.trim()
  const hit = await db.barcodes.get(code)
  if (hit) {
    const item = await db.items.get(hit.variant_id)
    return item ? { item, unitId: hit.unit_id, qty: null, price: null } : null
  }
  if (!/^2\d{12}$/.test(code) || !validEan13(code)) return null
  const item = await db.items.where('plu').equals(code.slice(2, 7)).first()
  if (!item) return null
  const value = Number(code.slice(7, 12))
  return scaleMode === 'price'
    ? { item, unitId: null, qty: null, price: value / 100 }
    : { item, unitId: null, qty: value / 1000, price: null }
}
