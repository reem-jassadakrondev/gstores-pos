import 'fake-indexeddb/auto'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { db, setMeta } from '../../resources/pos/lib/db'
import { state } from '../../resources/pos/lib/state'
import { enqueue, nextNumber, pushOutbox, rememberTerminal } from '../../resources/pos/lib/sync'

beforeEach(async () => {
  await db.delete()
  await db.open()
  await setMeta('token', 't')
  state.paired = true
})

const respond = (body: any, status = 200) => vi.fn(async () => ({ ok: status < 400, status, json: async () => body }))

describe('outbox', () => {
  it('keeps ops while offline and sends them in order when back', async () => {
    globalThis.fetch = vi.fn(async () => { throw new TypeError('offline') }) as any
    await enqueue('shift.open', { id: 'a' })
    await enqueue('sale', { id: 'b' })
    await pushOutbox()
    expect(state.online).toBe(false)
    expect(state.pending).toBe(2)

    const fetch = respond({ results: [{ status: 'ok' }, { status: 'duplicate' }] })
    globalThis.fetch = fetch as any
    await pushOutbox()

    const body = JSON.parse((fetch.mock.calls[0] as any)[1].body)
    expect(body.ops.map((o: any) => o.op)).toEqual(['shift.open', 'sale'])
    expect(state.pending).toBe(0)
  })

  it('marks rejected ops as failed instead of dropping them', async () => {
    globalThis.fetch = respond({ results: [{ status: 'error', message: 'Shift not found' }] }) as any
    await enqueue('sale', { id: 'x' })
    await pushOutbox()
    await pushOutbox()
    const failed = await db.outbox.toArray()
    expect(failed[0].status).toBe('failed')
    expect(failed[0].error).toBe('Shift not found')
    expect(state.failed).toBe(1)
  })

  it('a revoked token stops syncing but keeps the queue', async () => {
    globalThis.fetch = respond({ message: 'Unauthenticated.' }, 401) as any
    await enqueue('sale', { id: 'y' })
    await pushOutbox()
    expect(state.paired).toBe(false)
    expect(await db.outbox.count()).toBe(1)
  })
})

describe('numbering', () => {
  it('only moves forward and continues from the server after a reinstall', async () => {
    await rememberTerminal({ sequences: { sale: 41, credit_note: 1, z: 3 } })
    expect(await nextNumber('sale')).toBe(41)
    expect(await nextNumber('sale')).toBe(42)
    await rememberTerminal({ sequences: { sale: 10, credit_note: 1, z: 3 } })
    expect(await nextNumber('sale')).toBe(43)
    expect(await nextNumber('z')).toBe(3)
  })
})
