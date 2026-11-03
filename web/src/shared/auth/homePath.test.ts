import { describe, it, expect } from 'vitest'
import { homePathFor } from './homePath'

describe('homePathFor', () => {
  it('maps each role to its home path', () => {
    expect(homePathFor('CUSTOMER')).toBe('/book')
    expect(homePathFor('SERVICE_ADVISOR')).toBe('/staff')
    expect(homePathFor('WORKSHOP_FOREMAN')).toBe('/staff/workshop')
    expect(homePathFor('ADMIN')).toBe('/staff/master-data')
    expect(homePathFor(undefined)).toBe('/staff')
  })
})

