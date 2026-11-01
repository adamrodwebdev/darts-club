import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatEuro, formatInputNumber } from '@/i18n/format'
import type { BarItem, SaleFormat, Station, StockUnit } from '@/models/EventAccounts'
import { STOCK_MEASURES } from '@/models/StockMeasure'
import type { BarItemDraft } from '@/services/EventAccountsService'
import { useStockQuantity } from './useStockQuantity'

/** Format tel que saisi ; `key` stable pour la liste */
export interface FormatRow {
  key: number
  name: string
  volume: string
  price: string
  sold: string
}

/** Plafond de l'API : un article se vend en six formats au plus */
export const FORMATS_MAX = 6

const MONEY = /^\d{1,6}([.,]\d{1,2})?$/

/** « 95,00 » → 9 500 centimes ; null si ce n'est pas un montant */
function cents(text: string): number | null {
  const value = text.trim()
  return MONEY.test(value) ? Math.round(Number(value.replace(',', '.')) * 100) : null
}

/** Nombre entier saisi, vide pour zéro ; null si ce n'en est pas un */
function count(text: string): number | null {
  const value = text.trim()
  return value === '' ? 0 : /^\d{1,6}$/.test(value) ? Number(value) : null
}

/**
 * Saisie d'un article de la carte : textes tels que tapés, contenances relues (« 30 L », « 25 cl »),
 * aperçu du stock et du coût, puis brouillon pour l'API, qui reste juge des règles.
 */
export function useMenuItemForm(item: BarItem | null) {
  const { t } = useI18n()
  const quantity = useStockQuantity()
  let nextKey = 0

  const price = (decimal: string): string => formatInputNumber(Number(decimal), 2)
  const row = (format: SaleFormat | null): FormatRow => ({
    key: nextKey++,
    name: format?.name ?? '',
    volume: format?.volume == null || item?.unit !== 'litre' ? '' : quantity.input('litre', format.volume),
    price: format ? price(format.price) : '',
    sold: String(format?.sold ?? 0),
  })

  const label = ref(item?.label ?? '')
  const station = ref<Station>(item?.station ?? 'bar')
  const unit = ref<StockUnit>(item?.unit ?? 'unite')
  const garnishes = ref(item?.garnishes.join(', ') ?? '')
  const alcoholic = ref(item?.alcoholic ?? false)
  const containerName = ref(item?.container.name ?? '')
  const containerSize = ref(item ? quantity.input(item.unit, item.container.size) : '1')
  const planned = ref(String(item?.planned ?? 0))
  const bought = ref(String(item?.bought ?? 0))
  const returned = ref(String(item?.returned ?? 0))
  const purchasePrice = ref(item ? price(item.purchasePrice) : '')
  const formats = ref<FormatRow[]>(item ? item.formats.map(row) : [row(null)])

  /** Au litre, chaque format a sa contenance ; ce qui se compte se vend à l'unité */
  const byTheLitre = computed(() => unit.value === 'litre')
  const size = computed(() => STOCK_MEASURES[unit.value].parse(containerSize.value, 'container'))
  const volume = (text: string): number | null | undefined => (text.trim() === '' ? null : (STOCK_MEASURES.litre.parse(text, 'format') ?? undefined))

  /** Erreurs vues avant l'envoi : contenances et nombres illisibles (clés des champs de l'API) */
  const errors = computed<Record<string, string>>(() => {
    const found: Record<string, string> = {}
    if (size.value === null) found.containerSize = t(byTheLitre.value ? 'events.accounts.menu.sizeInvalid' : 'events.accounts.menu.countInvalid')
    for (const [key, text] of [['planned', planned.value], ['bought', bought.value], ['returned', returned.value]] as const) {
      if (count(text) === null) found[key] = t('events.accounts.menu.wholeNumber')
    }
    for (const format of formats.value) {
      if (byTheLitre.value && volume(format.volume) === undefined) found[`format${format.key}`] = t('events.accounts.menu.volumeInvalid')
      if (count(format.sold) === null) found[`sold${format.key}`] = t('events.accounts.menu.wholeNumber')
    }
    return found
  })

  /** « Stock de la soirée : 60 L · coût 190,00 € », tant que les nombres se lisent */
  const preview = computed<{ stock: string; cost: string } | null>(() => {
    const kept = (count(bought.value) ?? 0) - (count(returned.value) ?? 0)
    const unitPrice = cents(purchasePrice.value)
    if (size.value === null || kept < 0) return null
    return {
      stock: quantity.withUnit(unit.value, kept * size.value),
      cost: unitPrice === null ? '–' : formatEuro((kept * unitPrice) / 100),
    }
  })

  function addFormat(): void {
    if (formats.value.length < FORMATS_MAX) formats.value.push(row(null))
  }

  function removeFormat(key: number): void {
    if (formats.value.length > 1) formats.value = formats.value.filter((format) => format.key !== key)
  }

  /** Brouillon prêt à envoyer, ou null tant qu'une contenance reste illisible */
  function draft(): BarItemDraft | null {
    if (Object.keys(errors.value).length > 0 || size.value === null) return null
    const cuisine = station.value === 'cuisine'
    return {
      label: label.value.trim(),
      station: station.value,
      unit: unit.value,
      garnishes: cuisine ? garnishes.value.split(',').map((g) => g.trim()).filter((g) => g !== '') : [],
      alcoholic: !cuisine && alcoholic.value,
      containerName: containerName.value.trim() || null,
      containerSize: size.value,
      planned: count(planned.value) ?? 0,
      bought: count(bought.value) ?? 0,
      returned: count(returned.value) ?? 0,
      purchasePrice: purchasePrice.value.trim(),
      formats: formats.value.map((format) => ({
        name: format.name.trim(),
        volume: byTheLitre.value ? (volume(format.volume) ?? null) : 1,
        price: format.price.trim(),
        sold: count(format.sold) ?? 0,
      })),
    }
  }

  return {
    label,
    station,
    unit,
    garnishes,
    alcoholic,
    containerName,
    containerSize,
    planned,
    bought,
    returned,
    purchasePrice,
    formats,
    byTheLitre,
    errors,
    preview,
    addFormat,
    removeFormat,
    draft,
  }
}
