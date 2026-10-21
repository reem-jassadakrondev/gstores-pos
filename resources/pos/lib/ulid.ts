/** Lowercase ULIDs, monotonic within a millisecond, so offline-made ids sort by creation time. */
const ALPHABET = '0123456789abcdefghjkmnpqrstvwxyz'
let lastTime = 0
let lastRandom: number[] = []

export function ulid(now = Date.now()): string {
  if (now === lastTime) {
    // Increment the random part so ids made in the same millisecond still sort.
    for (let i = lastRandom.length - 1; i >= 0; i--) {
      if (lastRandom[i] < 31) {
        lastRandom[i]++
        break
      }
      lastRandom[i] = 0
    }
  } else {
    lastTime = now
    const bytes = crypto.getRandomValues(new Uint8Array(16))
    lastRandom = Array.from(bytes, (b) => b % 32)
  }
  let time = ''
  let t = now
  for (let i = 0; i < 10; i++) {
    time = ALPHABET[t % 32] + time
    t = Math.floor(t / 32)
  }
  return time + lastRandom.map((n) => ALPHABET[n]).join('')
}
