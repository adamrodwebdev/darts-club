import { enumLabel, type EnumKind } from './index'

export interface LabelledOption<T extends string = string> {
  readonly id: T
  readonly label: string
}

/**
 * Valeur d'énumération dont le libellé suit la langue courante : le getter est
 * relu à chaque rendu. Le libellé de l'API sert de repli pour une valeur inconnue.
 */
export function labelled<T extends string>(kind: EnumKind, id: T, apiLabel: string = id): LabelledOption<T> {
  return {
    id,
    get label() {
      return enumLabel(kind, id, apiLabel)
    },
  }
}
