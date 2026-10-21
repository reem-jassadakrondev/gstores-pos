import { describe, expect, it } from 'vitest'
import fixture from '../fixtures/sale-calc.json'
import { calculate } from '../../resources/pos/lib/calc'

// The same cases are checked against the PHP SaleCalculator (tests/Unit/SaleCalculatorTest.php).
describe('calculate matches the server', () => {
  for (const c of fixture as any[]) {
    it(c.name, () => {
      expect(calculate(c.input)).toEqual(c.expected)
    })
  }
})
