import { bool, list, obj, optStr, str } from './guards'

/** Pictogramme d'une clé : téléphone, ordinateur, ou clé d'un gestionnaire de mots de passe */
export type PasskeyDevice = 'phone' | 'computer' | 'key'

/** Libellés de l'API (appareil ou gestionnaire reconnu) et leur pictogramme ; les autres : 'key' */
const DEVICES: Readonly<Record<string, PasskeyDevice>> = {
  iPhone: 'phone',
  iPad: 'phone',
  Android: 'phone',
  'Samsung Pass': 'phone',
  Windows: 'computer',
  'Windows Hello': 'computer',
  Mac: 'computer',
  Chromebook: 'computer',
  Linux: 'computer',
  Chrome: 'computer',
}

export interface PasskeyData {
  id: string
  /** Appareil ou gestionnaire reconnu (« iPhone », « Windows Hello »…), vide s'il est inconnu */
  label: string
  /** Synchronisée par un gestionnaire (iCloud, Google…) : elle suit l'adhérent sur ses appareils */
  synced: boolean
  createdAt: Date
  lastUsedAt: Date | null
}

/** Clé d'accès enregistrée sur le compte. */
export class Passkey {
  declare readonly id: string
  declare readonly label: string
  declare readonly synced: boolean
  declare readonly createdAt: Date
  declare readonly lastUsedAt: Date | null

  constructor(data: PasskeyData) {
    Object.assign(this, data)
  }

  static parse(value: unknown): Passkey {
    const d = obj(value, 'clé d’accès')
    const lastUsedAt = optStr(d.lastUsedAt)
    return new Passkey({
      id: str(d.id),
      label: str(d.label),
      synced: bool(d.synced),
      createdAt: new Date(str(d.createdAt)),
      lastUsedAt: lastUsedAt === null ? null : new Date(lastUsedAt),
    })
  }

  /** Réponse { items } de l'API */
  static parseList(value: unknown): Passkey[] {
    return list(obj(value, 'clés d’accès').items, (item) => Passkey.parse(item))
  }

  get device(): PasskeyDevice {
    return DEVICES[this.label] ?? 'key'
  }
}
