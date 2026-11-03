import { describe, it, expect } from 'vitest'
import {
  validateEmail,
  validatePassword,
  validatePasswordConfirmation,
  validateFullName,
  validatePhone,
  normalizePhone,
} from './rules'

describe('rules (API §B4 adversarial input table, TC-00-34)', () => {
  it('rejects email array with validation error', () => {
    const res = validateEmail(['a@b.c'])
    expect(res.valid).toBe(false)
  })

  it('rejects email with 300 characters with validation error', () => {
    const longEmail = `${'a'.repeat(290)}@example.com`
    const res = validateEmail(longEmail)
    expect(res.valid).toBe(false)
  })

  it('rejects non-ASCII email ánh@mail.com with validation error', () => {
    const res = validateEmail('ánh@mail.com')
    expect(res.valid).toBe(false)
  })

  it('accepts valid ASCII email', () => {
    const res = validateEmail('hung@mail.com')
    expect(res.valid).toBe(true)
  })

  it('rejects password > 72 characters with validation error (400)', () => {
    const longPassword = 'a'.repeat(73)
    const res = validatePassword(longPassword)
    expect(res.valid).toBe(false)
    expect(res.code).toBe('VALIDATION_ERROR')
  })

  it('rejects password of 30 Vietnamese chars with letters and digit (> 72 bytes) with validation error (400)', () => {
    // 30 characters containing 3-byte UTF-8 accented characters
    const vietnamesePw = 'ệộựữ'.repeat(7) + 'a1' // 30 characters, 28 * 3 + 2 = 86 bytes
    expect(vietnamesePw.length).toBe(30)
    const byteLength = new TextEncoder().encode(vietnamesePw).length
    expect(byteLength).toBeGreaterThan(72)

    const res = validatePassword(vietnamesePw)
    expect(res.valid).toBe(false)
    expect(res.code).toBe('VALIDATION_ERROR')
  })

  it('rejects password 12345678 (no letter) as weak password (422)', () => {
    const res = validatePassword('12345678')
    expect(res.valid).toBe(false)
    expect(res.code).toBe('WEAK_PASSWORD')
  })

  it('rejects password abcdefgh (no digit) as weak password (422)', () => {
    const res = validatePassword('abcdefgh')
    expect(res.valid).toBe(false)
    expect(res.code).toBe('WEAK_PASSWORD')
  })

  it('rejects password shorter than 8 characters as weak password (422)', () => {
    const res = validatePassword('abc123')
    expect(res.valid).toBe(false)
    expect(res.code).toBe('WEAK_PASSWORD')
  })

  it('accepts valid password with >= 8 chars, letter and digit, <= 72 bytes', () => {
    const res = validatePassword('secretPassword123')
    expect(res.valid).toBe(true)
  })

  it('rejects passwordConfirmation when different from password', () => {
    const res = validatePasswordConfirmation('secret123', 'different123')
    expect(res.valid).toBe(false)
  })

  it('accepts passwordConfirmation matching password', () => {
    const res = validatePasswordConfirmation('secret123', 'secret123')
    expect(res.valid).toBe(true)
  })

  it('rejects whitespace-only fullName', () => {
    const res = validateFullName('   ')
    expect(res.valid).toBe(false)
  })

  it('accepts valid fullName with 2–100 characters', () => {
    const res = validateFullName('Nguyễn Văn Hùng')
    expect(res.valid).toBe(true)
  })

  it('rejects phone with non-allowed characters abc123', () => {
    const res = validatePhone('abc123')
    expect(res.valid).toBe(false)
  })

  it('rejects phone with > 15 digits', () => {
    const res = validatePhone('+84 90 312 3218 999 999')
    expect(res.valid).toBe(false)
  })

  it('accepts valid phone and normalises with leading +', () => {
    const res = validatePhone('+84 90 312 3218')
    expect(res.valid).toBe(true)
    expect(res.normalized).toBe('+84903123218')
  })

  it('normalises local phone number correctly', () => {
    expect(normalizePhone('0903 123 218')).toBe('0903123218')
    expect(normalizePhone('(028) 3812-3456')).toBe('02838123456')
  })
})
