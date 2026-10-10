import { describe, expect, it } from 'vitest'
import { RIFF, eighthDuration, midiToFrequency, riffDuration } from '../riff'

describe('riff « Triple Vingt »', () => {
  it('starts on low C in drop C tuning', () => {
    expect(RIFF[0]?.note).toBe(36)
    expect(midiToFrequency(36)).toBeCloseTo(65.41, 1)
    expect(midiToFrequency(69)).toBe(440)
  })

  it('lasts exactly two 4/4 bars at 92 BPM', () => {
    const eighths = RIFF.reduce((total, step) => total + step.eighths, 0)
    expect(eighths).toBe(16)
    expect(riffDuration()).toBeCloseTo(16 * eighthDuration(), 6)
    expect(riffDuration()).toBeCloseTo(5.217, 2)
  })
})
