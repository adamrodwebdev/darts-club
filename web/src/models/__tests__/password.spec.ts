import { describe, expect, it } from 'vitest'
import { meetsRule, PASSWORD_RULES } from '../PasswordPolicy'

const unmet = (password: string) => PASSWORD_RULES.filter((rule) => !meetsRule(password, rule))

describe('PasswordPolicy', () => {
  it('mirrors the API rules: 6 characters, a number, a special character', () => {
    expect(unmet('fl3ch!')).toEqual([])
    expect(unmet('password1234')).toEqual(['special'])
    expect(unmet('flèche')).toEqual(['digit', 'special'])
    expect(unmet('f3!')).toEqual(['length'])
  })

  it('counts accents as letters, spaces as special characters, an emoji as one character', () => {
    expect(meetsRule('é', 'special')).toBe(false)
    expect(meetsRule('é', 'special')).toBe(false)
    expect(meetsRule('deux mots', 'special')).toBe(true)
    expect(meetsRule('🎯🎯🎯🎯🎯', 'length')).toBe(false)
    expect(meetsRule('🎯🎯🎯🎯🎯🎯', 'length')).toBe(true)
  })
})
