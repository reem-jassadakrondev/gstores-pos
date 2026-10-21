import { describe, expect, it } from 'vitest'
import { displayWidth, encodeReceipt, tis620 } from '../../resources/pos/lib/escpos'
import { pbkdf2Hex, verifyPin } from '../../resources/pos/lib/pin'
import { crc16, promptPayPayload } from '../../resources/pos/lib/promptpay'
import { ulid } from '../../resources/pos/lib/ulid'

// Expected strings come from the PHP side (App\Support\Payments\PromptPay, hash_pbkdf2).
describe('promptpay', () => {
  it('matches the server payloads', () => {
    expect(crc16('123456789')).toBe('29B1')
    expect(promptPayPayload('081-234-5678', 50)).toBe('00020101021229370016A000000677010111011300668123456785802TH5303764540550.0063049948')
    expect(promptPayPayload('0105536112014')).toBe('00020101021129370016A000000677010111021301055361120145802TH53037646304C7F0')
    expect(promptPayPayload('0812345678', 1234.5)).toBe('00020101021229370016A000000677010111011300668123456785802TH530376454071234.50630414B2')
  })
})

describe('offline pin', () => {
  it('derives the same verifier as PHP hash_pbkdf2', async () => {
    const hash = '67e86fe17579fd76dfa08b594c235ca8b54053cc1d609654fea4e03cadd4877c'
    expect(await pbkdf2Hex('4826', '9f2c1e', 1000)).toBe(hash)
    expect(await verifyPin('4826', { salt: '9f2c1e', iterations: 1000, hash })).toBe(true)
    expect(await verifyPin('4827', { salt: '9f2c1e', iterations: 1000, hash })).toBe(false)
  })
})

describe('ulid', () => {
  it('is lowercase, 26 chars and sorts in creation order', () => {
    const ids = Array.from({ length: 50 }, () => ulid())
    expect(ids.every((id) => /^[0-9a-hjkmnp-tv-z]{26}$/.test(id))).toBe(true)
    expect([...ids].sort()).toEqual(ids)
    expect(new Set(ids).size).toBe(50)
  })
})

describe('escpos', () => {
  it('encodes Thai as TIS-620 and measures printed width', () => {
    expect(tis620('กข A')).toEqual([0xa1, 0xa2, 0x20, 0x41])
    expect(displayWidth('น้ำ')).toBe(2) // ้ is a tone mark (no column); ำ takes one
    expect(displayWidth('ที่นี่')).toBe(2) // ท + น; ี and ่ sit above
  })

  it('lays out a pair row to the full width', () => {
    const bytes = encodeReceipt([{ kind: 'pair', left: 'รวม', right: '10.00' }], { columns: 32, codePage: 21 })
    const text = Array.from(bytes)
    expect(text.slice(0, 5)).toEqual([0x1b, 0x40, 0x1b, 0x74, 21])
    const line = text.slice(5, text.indexOf(0x0a, 5))
    expect(line.length).toBe(32)
  })
})
