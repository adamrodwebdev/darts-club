import { i18n } from '@/i18n'

/** Validation des réponses de l'API à la frontière (pas de `any` dans l'app). */
export class InvalidResponseError extends Error {
  constructor(what: string) {
    super(i18n.global.t('errors.invalidResponse', { what }))
    this.name = 'InvalidResponseError'
  }
}

export type Json = Record<string, unknown>

export function obj(value: unknown, what = 'objet'): Json {
  if (typeof value !== 'object' || value === null || Array.isArray(value)) {
    throw new InvalidResponseError(what)
  }
  return value as Json
}

export function str(value: unknown, what = 'texte'): string {
  if (typeof value !== 'string') throw new InvalidResponseError(what)
  return value
}

export function optStr(value: unknown, what = 'texte'): string | null {
  return value === null || value === undefined ? null : str(value, what)
}

export function num(value: unknown, what = 'nombre'): number {
  if (typeof value !== 'number' || Number.isNaN(value)) throw new InvalidResponseError(what)
  return value
}

export function bool(value: unknown, what = 'booléen'): boolean {
  if (typeof value !== 'boolean') throw new InvalidResponseError(what)
  return value
}

/** Booléen ajouté par une version plus récente de l'API : valeur par défaut s'il est absent */
export function optBool(value: unknown, fallback: boolean, what = 'booléen'): boolean {
  return value === undefined ? fallback : bool(value, what)
}

export function list<T>(value: unknown, parse: (item: unknown) => T, what = 'liste'): T[] {
  if (!Array.isArray(value)) throw new InvalidResponseError(what)
  return value.map(parse)
}

export function strList(value: unknown, what = 'liste'): string[] {
  return list(value, (v) => str(v, what), what)
}
