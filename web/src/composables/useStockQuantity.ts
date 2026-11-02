import { useI18n } from 'vue-i18n'
import { formatInputNumber, formatNumber } from '@/i18n/format'
import type { StockUnit } from '@/models/EventAccounts'
import { STOCK_MEASURES } from '@/models/StockMeasure'

const VOLUME_KEYS = { cl: 'events.accounts.menu.cl', l: 'events.accounts.menu.l' } as const
const NOUN_KEYS = { unite: 'events.accounts.menu.units', portion: 'events.accounts.menu.portions' } as const

/** Quantités de stock dans la langue courante : « 44,5 L », « 25 cl », « 48 », « 120 portions » */
export function useStockQuantity() {
  const { t } = useI18n()

  /** Quantité lue : un volume au litre, un simple nombre sinon */
  function format(unit: StockUnit, amount: number): string {
    const quantity = STOCK_MEASURES[unit].quantity(amount)
    return quantity.unit === null ? formatNumber(quantity.value) : t(VOLUME_KEYS[quantity.unit], { n: formatNumber(quantity.value) })
  }

  /** Avec le nom de l'unité pour ce qui se compte : « 48 unités », « 120 portions » */
  function withUnit(unit: StockUnit, amount: number): string {
    return unit === 'litre' ? format(unit, amount) : t(NOUN_KEYS[unit], { n: formatNumber(amount) }, amount)
  }

  /** Texte repris dans un champ, relu par `STOCK_MEASURES[unit].parse` : « 30 L », « 25 cl », « 24 » (espace simple, plus facile à corriger) */
  function input(unit: StockUnit, amount: number): string {
    const quantity = STOCK_MEASURES[unit].quantity(amount)
    return quantity.unit === null ? String(quantity.value) : t(VOLUME_KEYS[quantity.unit], { n: formatInputNumber(quantity.value) }).replace(/\s/g, ' ')
  }

  return { format, withUnit, input }
}
