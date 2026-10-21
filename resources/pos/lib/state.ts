import { reactive } from 'vue'
import type { LocalShift, Staff } from './db'

/** App-wide reactive state. Persisted parts live in IndexedDB (db.meta); this is the in-memory view. */
export const state = reactive({
  ready: false,
  paired: false,
  terminal: null as any, // /terminal/me payload
  staff: null as Staff | null, // who is signed in at the till
  shift: null as LocalShift | null,
  online: navigator.onLine,
  pending: 0,
  failed: 0,
  lastSync: null as string | null,
  screen: 'sell' as 'sell' | 'history' | 'shift' | 'settings',
})

export const settings = () => state.terminal?.pos_settings ?? {}

export const can = (permission: string, who: Staff | null = state.staff) => !!who && who.permissions.includes(permission)
