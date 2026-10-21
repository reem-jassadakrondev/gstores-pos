import type { Row } from './receipt'

/**
 * Minimal ESC/POS encoder for 58/80 mm thermal printers. Thai is sent as TIS-620 (CP874);
 * the printer's Thai code page number differs by model, so it is a setting.
 */
const ESC = 0x1b
const GS = 0x1d

export function tis620(text: string): number[] {
  const out: number[] = []
  for (const ch of text) {
    const code = ch.codePointAt(0)!
    if (code < 0x80) out.push(code)
    else if (code >= 0x0e01 && code <= 0x0e5b) out.push(code - 0x0e00 + 0xa0)
    else out.push(0x3f) // '?'
  }
  return out
}

/** Printed width of Thai text: combining vowels and tone marks take no column. */
export function displayWidth(text: string): number {
  let width = 0
  for (const ch of text) {
    const c = ch.codePointAt(0)!
    const combining = c === 0x0e31 || (c >= 0x0e34 && c <= 0x0e3a) || (c >= 0x0e47 && c <= 0x0e4e)
    if (!combining) width++
  }
  return width
}

function wrap(text: string, columns: number): string[] {
  const lines: string[] = []
  let current = ''
  for (const ch of text) {
    if (displayWidth(current + ch) > columns) {
      lines.push(current)
      current = ''
    }
    current += ch
  }
  if (current || !lines.length) lines.push(current)
  return lines
}

export function encodeReceipt(rows: Row[], opts: { columns: number; codePage: number }): Uint8Array {
  const out: number[] = [ESC, 0x40, ESC, 0x74, opts.codePage]
  const text = (s: string) => out.push(...tis620(s), 0x0a)
  for (const row of rows) {
    switch (row.kind) {
      case 'line':
        text('-'.repeat(opts.columns))
        break
      case 'text':
        wrap(row.text, opts.columns).forEach(text)
        break
      case 'center': {
        out.push(ESC, 0x61, 1)
        if (row.bold) out.push(ESC, 0x45, 1)
        if (row.big) out.push(GS, 0x21, 0x01)
        wrap(row.text, row.big ? Math.floor(opts.columns) : opts.columns).forEach(text)
        out.push(GS, 0x21, 0x00, ESC, 0x45, 0, ESC, 0x61, 0)
        break
      }
      case 'pair': {
        if (row.bold) out.push(ESC, 0x45, 1)
        const space = opts.columns - displayWidth(row.left) - displayWidth(row.right)
        if (space >= 1) text(row.left + ' '.repeat(space) + row.right)
        else {
          wrap(row.left, opts.columns).forEach(text)
          text(' '.repeat(Math.max(0, opts.columns - displayWidth(row.right))) + row.right)
        }
        if (row.bold) out.push(ESC, 0x45, 0)
        break
      }
      case 'qr': {
        const data = Array.from(new TextEncoder().encode(row.data))
        const len = data.length + 3
        out.push(ESC, 0x61, 1)
        out.push(GS, 0x28, 0x6b, 4, 0, 0x31, 0x41, 0x32, 0x00) // model 2
        out.push(GS, 0x28, 0x6b, 3, 0, 0x31, 0x43, 0x06) // module size
        out.push(GS, 0x28, 0x6b, len & 0xff, len >> 8, 0x31, 0x50, 0x30, ...data)
        out.push(GS, 0x28, 0x6b, 3, 0, 0x31, 0x51, 0x30, 0x0a, ESC, 0x61, 0)
        break
      }
    }
  }
  // Feed and partial cut.
  out.push(0x0a, 0x0a, 0x0a, GS, 0x56, 0x42, 0x00)
  return new Uint8Array(out)
}

/** Drawer kick on pin 2 (most cash drawers wired to the receipt printer). */
export const OPEN_DRAWER = new Uint8Array([ESC, 0x70, 0x00, 0x19, 0xfa])
