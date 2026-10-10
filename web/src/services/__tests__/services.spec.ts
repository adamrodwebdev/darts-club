import { describe, expect, it } from 'vitest'
import { ApiClient } from '@/api/ApiClient'
import { AuthService } from '../AuthService'
import { PushService } from '../PushService'

describe('AuthService.hasSessionHint', () => {
  const api = new ApiClient()

  it('detects the non-sensitive session cookie', () => {
    expect(new AuthService(api, () => 'theme=dark; dc_session=1').hasSessionHint()).toBe(true)
  })

  it('ignores other cookies', () => {
    expect(new AuthService(api, () => 'dc_session_old=1; other=dc_session=1').hasSessionHint()).toBe(false)
  })
})

describe('PushService.decodeKey', () => {
  it('decodes base64url VAPID keys', () => {
    expect(Array.from(PushService.decodeKey('AQID_-8'))).toEqual([1, 2, 3, 255, 239])
  })
})
