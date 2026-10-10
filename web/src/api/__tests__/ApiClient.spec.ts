import { describe, expect, it, vi } from 'vitest'
import { ApiClient } from '../ApiClient'
import { ApiError } from '../ApiError'

function jsonResponse(status: number, body: unknown): Response {
  return new Response(JSON.stringify(body), { status, headers: { 'Content-Type': 'application/json' } })
}

describe('ApiClient', () => {
  it('sends JSON with same-origin credentials', async () => {
    const fetchFn = vi.fn<typeof fetch>().mockResolvedValue(jsonResponse(200, { ok: true }))
    const client = new ApiClient('', fetchFn)

    await expect(client.post('/api/x', { a: 1 })).resolves.toEqual({ ok: true })

    const [url, init] = fetchFn.mock.calls[0] as [string, RequestInit]
    expect(url).toBe('/api/x')
    expect(init.credentials).toBe('same-origin')
    expect(init.body).toBe('{"a":1}')
    expect((init.headers as Record<string, string>)['Content-Type']).toBe('application/json')
  })

  it('does not force a content type for FormData uploads', async () => {
    const fetchFn = vi.fn<typeof fetch>().mockResolvedValue(new Response(null, { status: 204 }))
    await new ApiClient('', fetchFn).post('/api/upload', new FormData())

    const init = fetchFn.mock.calls[0]?.[1] as RequestInit
    expect((init.headers as Record<string, string>)['Content-Type']).toBeUndefined()
  })

  it('maps Symfony validation violations per field', async () => {
    const fetchFn = vi.fn<typeof fetch>().mockResolvedValue(
      jsonResponse(422, { title: 'Validation Failed', violations: [{ propertyPath: 'email', title: 'Email invalide.' }] }),
    )

    const error = await new ApiClient('', fetchFn).post('/api/auth/register', {}).catch((e: unknown) => e)
    expect(error).toBeInstanceOf(ApiError)
    expect((error as ApiError).isValidation).toBe(true)
    expect((error as ApiError).violations).toEqual({ email: 'Email invalide.' })
  })

  it('uses the API error message and its stable code when present', async () => {
    const fetchFn = vi.fn<typeof fetch>().mockResolvedValue(jsonResponse(401, { error: 'Email address not verified.', code: 'email_not_verified' }))
    const error = (await new ApiClient('', fetchFn).get('/api/auth/me').catch((e: unknown) => e)) as ApiError

    expect(error.isUnauthorized).toBe(true)
    expect(error.message).toBe('Email address not verified.')
    expect(error.code).toBe('email_not_verified')
  })

  it('tells the API which language to answer in', async () => {
    const fetchFn = vi.fn<typeof fetch>().mockResolvedValue(jsonResponse(200, {}))
    await new ApiClient('', fetchFn, () => 'en').get('/api/x')

    const init = fetchFn.mock.calls[0]?.[1] as RequestInit
    expect((init.headers as Record<string, string>)['Accept-Language']).toBe('en')
  })

  it('turns network failures into a readable error', async () => {
    const fetchFn = vi.fn<typeof fetch>().mockRejectedValue(new TypeError('Failed to fetch'))
    const error = (await new ApiClient('', fetchFn).get('/api/x').catch((e: unknown) => e)) as ApiError

    expect(error.status).toBe(0)
    expect(error.message).toContain('réseau')
  })
})
