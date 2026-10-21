import { api, ApiError, isOfflineError } from './api'
import { applyCatalog } from './catalog'
import { db, getMeta, setMeta, type OutboxOp } from './db'
import { state } from './state'

/**
 * Offline-first sync. Everything the till does is written locally and queued in `outbox`;
 * the queue is pushed in order whenever there is a network. The server answers per op:
 * ok / duplicate → done, error → kept as "failed" and shown in Settings.
 */

export async function enqueue(op: string, data: any): Promise<void> {
  // Plain copy: IndexedDB can't store Vue's reactive proxies.
  await db.outbox.add({ op, data: JSON.parse(JSON.stringify(data)), status: 'pending', created_at: new Date().toISOString() })
  await refreshCounts()
  void pushOutbox()
}

export async function refreshCounts(): Promise<void> {
  state.pending = await db.outbox.where('status').equals('pending').count()
  state.failed = await db.outbox.where('status').equals('failed').count()
}

let running: Promise<void> | null = null

/** Sends the queue. Concurrent callers share the push already in flight. */
export function pushOutbox(): Promise<void> {
  if (!state.paired) return Promise.resolve()
  running ??= push().finally(() => (running = null))
  return running
}

async function push(): Promise<void> {
  try {
    for (;;) {
      const batch: OutboxOp[] = await db.outbox.where('status').equals('pending').limit(50).sortBy('seq')
      if (!batch.length) break
      const { results } = await api('sync', { method: 'POST', body: { ops: batch.map(({ op, data }) => ({ op, data })) } })
      for (let i = 0; i < batch.length; i++) {
        const result = results[i]
        if (result.status === 'error') await db.outbox.update(batch[i].seq!, { status: 'failed', error: result.message })
        else await db.outbox.delete(batch[i].seq!)
      }
    }
    state.online = true
    state.lastSync = new Date().toISOString()
  } catch (e) {
    handleNetworkError(e)
  } finally {
    await refreshCounts()
  }
}

/** Puts failed ops back in the queue (e.g. after a manager fixed a permission). */
export async function retryFailed(): Promise<void> {
  await db.outbox.where('status').equals('failed').modify({ status: 'pending', error: undefined })
  await pushOutbox()
}

/** Terminal info, staff list and catalog changes since the last pull. */
export async function pull(full = false): Promise<void> {
  try {
    const me = await api('me')
    await rememberTerminal(me)
    const staff = await api('staff')
    await db.transaction('rw', db.staff, async () => {
      await db.staff.clear()
      await db.staff.bulkPut(staff.data)
    })
    const since = full ? null : await getMeta<string>('catalog_synced_at')
    const catalog = await api('catalog' + (since ? `?since=${encodeURIComponent(since)}` : ''))
    await applyCatalog(catalog)
    await setMeta('catalog_synced_at', catalog.synced_at)
    state.online = true
    state.lastSync = new Date().toISOString()
  } catch (e) {
    handleNetworkError(e)
  }
}

/** Stores /me and moves the local counters forward to where the server says numbering continues. */
export async function rememberTerminal(me: any): Promise<void> {
  state.terminal = me
  await setMeta('terminal', me)
  const seq = (await getMeta<any>('seq')) ?? { sale: 1, credit_note: 1, z: 1 }
  await setMeta('seq', {
    sale: Math.max(seq.sale, me.sequences.sale),
    credit_note: Math.max(seq.credit_note, me.sequences.credit_note),
    z: Math.max(seq.z, me.sequences.z),
  })
}

/** Next running number of a kind; the counter only ever moves forward. */
export async function nextNumber(kind: 'sale' | 'credit_note' | 'z'): Promise<number> {
  return db.transaction('rw', db.meta, async () => {
    const seq = (await getMeta<any>('seq')) ?? { sale: 1, credit_note: 1, z: 1 }
    const n = seq[kind]
    await setMeta('seq', { ...seq, [kind]: n + 1 })
    return n
  })
}

function handleNetworkError(e: unknown): void {
  if (isOfflineError(e)) {
    state.online = false
    return
  }
  if (e instanceof ApiError && e.status === 401) {
    // Token revoked (unpaired in the back-office). Keep the outbox: nothing sold is thrown away.
    state.paired = false
  }
  console.warn('sync', e)
}

export function startSyncLoop(): void {
  window.addEventListener('online', () => {
    state.online = true
    void pushOutbox().then(() => pull())
  })
  window.addEventListener('offline', () => (state.online = false))
  setInterval(() => void pushOutbox(), 20_000)
  setInterval(() => void pull(), 5 * 60_000)
}
