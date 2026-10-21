import { getMeta } from './db'

export class ApiError extends Error {
  constructor(public status: number, message: string, public code?: string) {
    super(message)
  }
}

/** Calls the terminal API with the device token. Throws ApiError (or TypeError when offline). */
export async function api<T = any>(path: string, options: { method?: string; body?: any; token?: string } = {}): Promise<T> {
  const token = options.token ?? (await getMeta<string>('token'))
  const res = await fetch(`/api/v1/terminal/${path}`, {
    method: options.method ?? 'GET',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
  })
  const json = await res.json().catch(() => ({}))
  if (!res.ok) throw new ApiError(res.status, json.message ?? `HTTP ${res.status}`, json.code)
  return json as T
}

export const isOfflineError = (e: unknown) => e instanceof TypeError
