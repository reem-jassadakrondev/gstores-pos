import 'fake-indexeddb/auto'
import { beforeEach, describe, expect, it } from 'vitest'
import { applyCatalog, resolveBarcode, resolvePrice } from '../../resources/pos/lib/catalog'
import { db } from '../../resources/pos/lib/db'

const product = (id: string, extra: any = {}) => ({
  id, category_id: null, name: `P ${id}`, type: 'goods', base_unit: 'ขวด', vat_type: 'vat', track_stock: true,
  track_lots: false, is_weighable: false, plu: null, is_active: true,
  units: [{ id: `${id}-case`, name: 'ลัง', factor: '12.000' }],
  variants: [{ id: `${id}-v`, name: '', sku: `SKU${id}`, price: '10.00', is_active: true, sort: 0,
    barcodes: [{ code: `885${id}`, unit_id: null }, { code: `1885${id}`, unit_id: `${id}-case` }],
    prices: [
      { unit_id: `${id}-case`, channel: 'store', branch_specific: false, min_qty: '1.000', price: '110.00' },
      { unit_id: `${id}-case`, channel: 'store', branch_specific: false, min_qty: '10.000', price: '100.00' },
      { unit_id: null, channel: 'store', branch_specific: true, min_qty: '1.000', price: '9.50' },
    ] }],
  ...extra,
})

beforeEach(async () => {
  await db.delete()
  await db.open()
})

describe('catalog cache', () => {
  it('full download then delta replaces changed products and drops deleted ones', async () => {
    await applyCatalog({ full: true, categories: [], products: [product('1'), product('2')], deleted: {}, stock: [{ variant_id: '1-v', qty: '5.000' }] })
    expect(await db.items.count()).toBe(2)

    const changed = product('1')
    changed.variants[0].barcodes = [{ code: '999', unit_id: null }]
    await applyCatalog({ full: false, categories: [], products: [changed], deleted: { products: ['2'] }, stock: [] })

    expect(await db.items.count()).toBe(1)
    expect(await db.barcodes.get('8851')).toBeUndefined()
    expect((await db.barcodes.get('999'))?.variant_id).toBe('1-v')
    expect(Number((await db.stock.get('1-v'))?.qty)).toBe(5)
  })

  it('resolves prices like the server: branch price, unit tiers, factor fallback', async () => {
    await applyCatalog({ full: true, categories: [], products: [product('1')], deleted: {}, stock: [] })
    const item = (await db.items.get('1-v'))!
    expect(resolvePrice(item, null)).toBe(9.5)
    expect(resolvePrice(item, '1-case', 2)).toBe(110)
    expect(resolvePrice(item, '1-case', 10)).toBe(100)
    item.prices = []
    expect(resolvePrice(item, '1-case')).toBe(120)
  })

  it('resolves normal and scale barcodes', async () => {
    await applyCatalog({ full: true, categories: [], products: [product('1'), product('7', { is_weighable: true, plu: '00123' })], deleted: {}, stock: [] })
    expect((await resolveBarcode('18851', 'weight'))?.unitId).toBe('1-case')

    let code = '200012301250'
    let sum = 0
    for (let i = 0; i < 12; i++) sum += Number(code[i]) * (i % 2 ? 3 : 1)
    code += (10 - (sum % 10)) % 10
    const weighed = await resolveBarcode(code, 'weight')
    expect(weighed?.item.id).toBe('7-v')
    expect(weighed?.qty).toBe(1.25)
    expect((await resolveBarcode(code, 'price'))?.price).toBe(12.5)
    expect(await resolveBarcode('2000123012500', 'weight')).toBeNull()
  })
})
