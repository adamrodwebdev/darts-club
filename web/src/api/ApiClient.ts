import { ApiError } from './ApiError'

type Body = Record<string, unknown> | FormData | undefined

/**
 * Client HTTP de l'API. Session par cookie (même origine via le proxy),
 * aucun jeton stocké côté navigateur.
 */
export class ApiClient {
  constructor(
    private readonly baseUrl = '',
    private readonly fetchFn: typeof fetch = (...args) => fetch(...args),
    /** Langue de l'interface, transmise à l'API (messages et emails traduits) */
    private readonly language: () => string = () => document.documentElement.lang || 'fr',
  ) {}

  get(path: string): Promise<unknown> {
    return this.request('GET', path)
  }

  post(path: string, body?: Body): Promise<unknown> {
    return this.request('POST', path, body)
  }

  put(path: string, body?: Body): Promise<unknown> {
    return this.request('PUT', path, body)
  }

  patch(path: string, body?: Body): Promise<unknown> {
    return this.request('PATCH', path, body)
  }

  delete(path: string, body?: Body): Promise<unknown> {
    return this.request('DELETE', path, body)
  }

  async request(method: string, path: string, body?: Body): Promise<unknown> {
    const headers: Record<string, string> = { Accept: 'application/json', 'Accept-Language': this.language() }
    let payload: BodyInit | undefined

    if (body instanceof FormData) {
      payload = body
    } else if (body !== undefined) {
      headers['Content-Type'] = 'application/json'
      payload = JSON.stringify(body)
    }

    let response: Response
    try {
      response = await this.fetchFn(this.baseUrl + path, {
        method,
        headers,
        body: payload,
        credentials: 'same-origin',
      })
    } catch {
      throw new ApiError(0, 'Connexion impossible, vérifie ton réseau.')
    }

    const data = await ApiClient.readJson(response)
    if (!response.ok) {
      throw ApiError.fromBody(response.status, data)
    }

    return data
  }

  private static async readJson(response: Response): Promise<unknown> {
    if (response.status === 204) return null
    const type = response.headers.get('Content-Type') ?? ''
    if (!type.includes('json')) return null

    try {
      return await response.json()
    } catch {
      return null
    }
  }
}
