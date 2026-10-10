import { intlLocale } from './index'

/**
 * Formats Intl dans la langue courante. Les fonctions lisent la langue à chaque
 * appel : utilisées dans un rendu, elles suivent un changement de langue.
 */
const cache = new Map<string, Intl.DateTimeFormat | Intl.NumberFormat | Intl.RelativeTimeFormat>()

function cached<T extends Intl.DateTimeFormat | Intl.NumberFormat | Intl.RelativeTimeFormat>(kind: string, options: object, create: (locale: string) => T): T {
  const locale = intlLocale()
  const key = `${kind}|${locale}|${JSON.stringify(options)}`
  let formatter = cache.get(key)
  if (!formatter) {
    formatter = create(locale)
    cache.set(key, formatter)
  }
  return formatter as T
}

/** Fuseau du club : les horaires d'événements sont affichés à l'heure de Paris. */
export const CLUB_TIME_ZONE = 'Europe/Paris'

export function formatDate(date: Date, options: Intl.DateTimeFormatOptions = { dateStyle: 'long' }): string {
  return cached('date', options, (locale) => new Intl.DateTimeFormat(locale, options)).format(date)
}

export function formatDateTime(date: Date): string {
  return formatDate(date, { dateStyle: 'long', timeStyle: 'short' })
}

/** « 30.50 » → « 30,50 € » (fr) / « €30.50 » (en) */
export function formatEuro(decimal: string | number): string {
  const options = { style: 'currency', currency: 'EUR' } as const
  return cached('number', options, (locale) => new Intl.NumberFormat(locale, options)).format(Number(decimal))
}

export function formatNumber(value: number): string {
  return cached('number', {}, (locale) => new Intl.NumberFormat(locale)).format(value)
}

/** « il y a 3 heures », « dans 2 jours » ; null sous la minute (« à l'instant » est traduit à part). */
export function formatRelative(date: Date, now = Date.now()): string | null {
  const seconds = Math.round((date.getTime() - now) / 1000)
  const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
  ]
  const formatter = cached('relative', { numeric: 'auto' }, (locale) => new Intl.RelativeTimeFormat(locale, { numeric: 'auto' }))
  for (const [unit, size] of units) {
    if (Math.abs(seconds) >= size) return formatter.format(Math.round(seconds / size), unit)
  }
  return null
}
