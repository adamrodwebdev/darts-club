/** Erreur HTTP de l'API, avec les violations de validation par champ. */
export class ApiError extends Error {
  constructor(
    readonly status: number,
    message: string,
    readonly violations: Readonly<Record<string, string>> = {},
  ) {
    super(message)
    this.name = 'ApiError'
  }

  get isUnauthorized(): boolean {
    return this.status === 401
  }

  get isValidation(): boolean {
    return this.status === 422
  }

  get isRateLimited(): boolean {
    return this.status === 429
  }

  /** Construit l'erreur à partir d'un corps JSON Symfony (problem details ou { error }). */
  static fromBody(status: number, body: unknown): ApiError {
    const data = typeof body === 'object' && body !== null ? (body as Record<string, unknown>) : {}
    const violations: Record<string, string> = {}

    if (Array.isArray(data.violations)) {
      for (const v of data.violations) {
        if (typeof v === 'object' && v !== null) {
          const { propertyPath, title, message } = v as Record<string, unknown>
          const text = typeof message === 'string' ? message : title
          if (typeof propertyPath === 'string' && typeof text === 'string' && !(propertyPath in violations)) {
            violations[propertyPath] = text
          }
        }
      }
    }

    const message = [data.error, data.detail, data.title].find((m): m is string => typeof m === 'string' && m !== '')

    return new ApiError(status, message ?? ApiError.defaultMessage(status), violations)
  }

  private static defaultMessage(status: number): string {
    if (status === 429) return 'Trop de tentatives, réessaie dans quelques minutes.'
    if (status >= 500) return 'Le serveur rencontre un problème, réessaie plus tard.'
    return 'La requête a échoué.'
  }
}
