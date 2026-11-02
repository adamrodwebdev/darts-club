import type { StockUnit } from './EventAccounts'

/**
 * Quantités d'un article selon son unité de stock. Au litre, l'API compte en centilitres (fût de
 * 30 L = 3 000) ; à l'unité et à la portion, en nombres entiers. Règles pures : l'affichage
 * traduit l'unité, la saisie tolère « 30 L », « 25 cl », « 1,5 l » ou un nombre seul.
 */

/** Plafond de l'API pour une contenance */
export const SIZE_MAX = 100_000

/** Champ saisi : un nombre seul est en litres pour un contenant, en centilitres pour un format */
export type MeasureField = 'container' | 'format'

/** Quantité prête à afficher ; `unit` null pour un simple nombre */
export interface Quantity {
  value: number
  unit: 'cl' | 'l' | null
}

export interface StockMeasure {
  /** 4 450 cl → 44,5 L ; 25 cl → 25 cl ; 48 → 48 */
  quantity(amount: number): Quantity
  /** Texte saisi → unités de stock ; null si illisible ou hors limites */
  parse(text: string, field: MeasureField): number | null
}

const NUMBER = /^(\d+(?:[.,]\d+)?)\s*([a-z]*)$/i
const LITRE_SUFFIXES = ['l', 'litre', 'litres', 'liter', 'liters']
const CENTILITRE_SUFFIXES = ['cl', 'centilitre', 'centilitres', 'centiliter', 'centiliters']

function inRange(amount: number): number | null {
  return Number.isInteger(amount) && amount >= 1 && amount <= SIZE_MAX ? amount : null
}

const BY_THE_LITRE: StockMeasure = {
  quantity: (amount) => (Math.abs(amount) >= 100 ? { value: amount / 100, unit: 'l' } : { value: amount, unit: 'cl' }),
  parse(text, field) {
    const match = NUMBER.exec(text.trim())
    if (!match) return null
    const [, digits = '', unit = ''] = match
    const value = Number(digits.replace(',', '.'))
    const suffix = unit.toLowerCase()
    const litres = suffix === '' ? field === 'container' : LITRE_SUFFIXES.includes(suffix)
    if (!litres && suffix !== '' && !CENTILITRE_SUFFIXES.includes(suffix)) return null
    // Au centilitre près : « 0,33 L » = 33 cl ; « 33,5 cl » est refusé
    return inRange(litres ? Math.round(value * 100) : value)
  },
}

const COUNTED: StockMeasure = {
  quantity: (amount) => ({ value: amount, unit: null }),
  parse: (text) => (/^\d+$/.test(text.trim()) ? inRange(Number(text.trim())) : null),
}

export const STOCK_MEASURES: Readonly<Record<StockUnit, StockMeasure>> = {
  litre: BY_THE_LITRE,
  unite: COUNTED,
  portion: COUNTED,
}
