import { describe, expect, it } from 'vitest'
import { SONG } from '../song'
import { formatClock } from '@/i18n/format'

describe('hymne « Triple Vingt »', () => {
  it('splits the recording into parts in playing order', () => {
    const starts = SONG.sections.map((section) => section.start)
    expect(starts[0]).toBe(0)
    expect([...starts].sort((a, b) => a - b)).toEqual(starts)
    expect(new Set(starts).size).toBe(starts.length)
    expect(Math.max(...starts)).toBeLessThan(SONG.duration)
  })

  it('offers Opus first and MP3 as the universal fallback', () => {
    expect(SONG.recordings.map((recording) => recording.type)).toEqual(['audio/webm; codecs=opus', 'audio/mpeg'])
    for (const recording of SONG.recordings) expect(recording.src).toMatch(/^\/audio\/triple-vingt\.(webm|mp3)$/)
  })

  it('shows positions as minutes and seconds', () => {
    expect(formatClock(0)).toBe('0:00')
    expect(formatClock(100.9)).toBe('1:40')
    expect(formatClock(SONG.duration)).toBe('5:55')
  })
})
