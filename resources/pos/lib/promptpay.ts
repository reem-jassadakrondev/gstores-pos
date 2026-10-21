/** Thai QR (PromptPay) payload — same as App\Support\Payments\PromptPay. */
const AID = 'A000000677010111'

const tag = (id: string, value: string) => id + String(value.length).padStart(2, '0') + value

export function crc16(data: string): string {
  let crc = 0xffff
  for (let i = 0; i < data.length; i++) {
    crc ^= data.charCodeAt(i) << 8
    for (let bit = 0; bit < 8; bit++) {
      crc = crc & 0x8000 ? ((crc << 1) ^ 0x1021) & 0xffff : (crc << 1) & 0xffff
    }
  }
  return crc.toString(16).toUpperCase().padStart(4, '0')
}

export function promptPayPayload(id: string, amount?: number | null): string {
  const digits = id.replace(/\D/g, '')
  let target: string
  if (digits.length >= 15) target = tag('03', digits)
  else if (digits.length === 13) target = tag('02', digits)
  else if (digits.length >= 9) target = tag('01', ('66' + digits.replace(/^0+/, '')).padStart(13, '0'))
  else throw new Error('PromptPay ID must be a phone number, tax ID or e-wallet ID.')

  const data =
    tag('00', '01') +
    tag('01', amount != null ? '12' : '11') +
    tag('29', tag('00', AID) + target) +
    tag('58', 'TH') +
    tag('53', '764') +
    (amount != null ? tag('54', amount.toFixed(2)) : '') +
    '6304'
  return data + crc16(data)
}
