/** Offline PIN check against the server's PBKDF2-SHA256 verifier (OrganizationMember::setPin). */
export interface PinVerifier {
  salt: string
  hash: string
  iterations: number
}

export async function pbkdf2Hex(pin: string, salt: string, iterations: number): Promise<string> {
  const enc = new TextEncoder()
  const key = await crypto.subtle.importKey('raw', enc.encode(pin), 'PBKDF2', false, ['deriveBits'])
  const bits = await crypto.subtle.deriveBits({ name: 'PBKDF2', hash: 'SHA-256', salt: enc.encode(salt), iterations }, key, 256)
  return Array.from(new Uint8Array(bits), (b) => b.toString(16).padStart(2, '0')).join('')
}

export async function verifyPin(pin: string, verifier: PinVerifier): Promise<boolean> {
  const hash = await pbkdf2Hex(pin, verifier.salt, verifier.iterations)
  // Constant-time compare.
  let diff = hash.length ^ verifier.hash.length
  for (let i = 0; i < Math.min(hash.length, verifier.hash.length); i++) diff |= hash.charCodeAt(i) ^ verifier.hash.charCodeAt(i)
  return diff === 0
}
