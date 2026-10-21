import { getMeta, setMeta } from './db'
import { encodeReceipt, OPEN_DRAWER } from './escpos'
import type { Row } from './receipt'

/**
 * Printing. "browser" prints an HTML receipt through the system print dialog — works on every
 * device (PC printer drivers, Android print services such as Sunmi's built-in printer, AirPrint).
 * The ESC/POS modes talk to a thermal printer directly, without a dialog:
 *   usb — WebUSB (Chrome/Edge, PC & Android), serial — Web Serial (Chrome/Edge desktop),
 *   bluetooth — Web Bluetooth BLE printers (Chrome/Android). iPad Safari supports none of
 *   these, so iPad uses "browser" (AirPrint) until a native print bridge exists.
 */
export interface PrinterConfig {
  mode: 'browser' | 'usb' | 'serial' | 'bluetooth' | 'none'
  paperWidth: 58 | 80
  codePage: number
  openDrawer: boolean
}

export const defaultPrinter = (paperWidth = 80): PrinterConfig => ({ mode: 'browser', paperWidth: paperWidth as 58 | 80, codePage: 21, openDrawer: true })

export async function printerConfig(): Promise<PrinterConfig> {
  return { ...defaultPrinter(), ...((await getMeta<PrinterConfig>('printer')) ?? {}) }
}

export const savePrinterConfig = (config: PrinterConfig) => setMeta('printer', config)

let usbDevice: any = null
let serialPort: any = null
let bleCharacteristic: any = null

/** Asks the user to pick the printer (must run from a click). */
export async function connectPrinter(mode: PrinterConfig['mode']): Promise<void> {
  const nav = navigator as any
  if (mode === 'usb') {
    usbDevice = await nav.usb.requestDevice({ filters: [] })
  } else if (mode === 'serial') {
    serialPort = await nav.serial.requestPort()
  } else if (mode === 'bluetooth') {
    const device = await nav.bluetooth.requestDevice({ acceptAllDevices: true, optionalServices: ['000018f0-0000-1000-8000-00805f9b34fb'] })
    const server = await device.gatt.connect()
    const service = await server.getPrimaryService('000018f0-0000-1000-8000-00805f9b34fb')
    bleCharacteristic = await service.getCharacteristic('00002af1-0000-1000-8000-00805f9b34fb')
  }
}

async function sendBytes(mode: PrinterConfig['mode'], bytes: Uint8Array): Promise<void> {
  const nav = navigator as any
  if (mode === 'usb') {
    usbDevice ??= (await nav.usb.getDevices())[0]
    if (!usbDevice) throw new Error('ยังไม่ได้เชื่อมต่อเครื่องพิมพ์ USB')
    if (!usbDevice.opened) {
      await usbDevice.open()
      await usbDevice.selectConfiguration(1)
      await usbDevice.claimInterface(usbDevice.configuration.interfaces[0].interfaceNumber)
    }
    const endpoint = usbDevice.configuration.interfaces[0].alternate.endpoints.find((e: any) => e.direction === 'out')
    await usbDevice.transferOut(endpoint.endpointNumber, bytes)
  } else if (mode === 'serial') {
    serialPort ??= (await nav.serial.getPorts())[0]
    if (!serialPort) throw new Error('ยังไม่ได้เชื่อมต่อเครื่องพิมพ์ Serial')
    if (!serialPort.writable) await serialPort.open({ baudRate: 9600 })
    const writer = serialPort.writable.getWriter()
    await writer.write(bytes)
    writer.releaseLock()
  } else if (mode === 'bluetooth') {
    if (!bleCharacteristic) throw new Error('ยังไม่ได้เชื่อมต่อเครื่องพิมพ์ Bluetooth')
    for (let i = 0; i < bytes.length; i += 180) await bleCharacteristic.writeValueWithoutResponse(bytes.slice(i, i + 180))
  }
}

export async function printRows(rows: Row[], opts: { openDrawer?: boolean } = {}): Promise<void> {
  const config = await printerConfig()
  if (config.mode === 'none') return
  if (config.mode === 'browser') return printHtml(rows, config.paperWidth)
  const columns = config.paperWidth === 58 ? 32 : 48
  await sendBytes(config.mode, encodeReceipt(rows, { columns, codePage: config.codePage }))
  if (opts.openDrawer && config.openDrawer) await sendBytes(config.mode, OPEN_DRAWER)
}

const esc = (s: string) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]!)

export function rowsToHtml(rows: Row[]): string {
  return rows
    .map((r) => {
      switch (r.kind) {
        case 'line':
          return '<hr>'
        case 'text':
          return `<div>${esc(r.text)}</div>`
        case 'center':
          return `<div class="c${r.bold ? ' b' : ''}${r.big ? ' big' : ''}">${esc(r.text)}</div>`
        case 'pair':
          return `<div class="p${r.bold ? ' b' : ''}"><span>${esc(r.left)}</span><span>${esc(r.right)}</span></div>`
        case 'qr':
          return ''
      }
    })
    .join('')
}

function printHtml(rows: Row[], width: number): Promise<void> {
  return new Promise((resolve) => {
    const frame = document.createElement('iframe')
    frame.style.cssText = 'position:fixed;width:0;height:0;border:0;right:0;bottom:0'
    document.body.appendChild(frame)
    const doc = frame.contentDocument!
    doc.open()
    doc.write(`<!doctype html><html><head><meta charset="utf-8"><style>
      @page { size: ${width}mm auto; margin: 2mm }
      body { font: 12px/1.35 'Sarabun', 'Tahoma', sans-serif; width: ${width - 6}mm; margin: 0; color: #000 }
      .c { text-align: center } .b { font-weight: 700 } .big { font-size: 15px }
      .p { display: flex; justify-content: space-between; gap: 6px }
      hr { border: 0; border-top: 1px dashed #000; margin: 4px 0 }
    </style></head><body>${rowsToHtml(rows)}</body></html>`)
    doc.close()
    frame.contentWindow!.onafterprint = () => {
      frame.remove()
      resolve()
    }
    setTimeout(() => {
      frame.contentWindow!.focus()
      frame.contentWindow!.print()
      setTimeout(() => {
        frame.remove()
        resolve()
      }, 1000)
    }, 50)
  })
}
