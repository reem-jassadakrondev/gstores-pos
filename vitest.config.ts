import { defineConfig } from 'vitest/config'

// Unit tests for the POS app (resources/pos). Kept apart from vite.config.js so the
// Laravel plugin isn't loaded for tests.
export default defineConfig({
  test: {
    include: ['tests/js/**/*.test.ts'],
    environment: 'node',
  },
})
