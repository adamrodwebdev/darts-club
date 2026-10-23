import { isLocale, type Locale } from '@/i18n/locales'
import { THEME_PREFERENCES, type ThemePreference } from '@/preferences/ThemeController'
import { parseBadges, type Badge } from './Badge'
import { bool, obj, optStr, str, strList } from './guards'

/** Langue et thème enregistrés sur le compte */
export interface DisplayPreferences {
  language: Locale
  theme: ThemePreference
}

function parsePreferences(value: unknown): DisplayPreferences | null {
  if (value === undefined || value === null) return null
  const d = obj(value, 'préférences')
  const language = str(d.language)
  const theme = str(d.theme)
  return isLocale(language) && (THEME_PREFERENCES as readonly string[]).includes(theme)
    ? { language, theme: theme as ThemePreference }
    : null
}

export const BOARD_ROLES = ['ROLE_SECRETARY', 'ROLE_TREASURER', 'ROLE_PRESIDENT', 'ROLE_ADMIN'] as const

export interface ProfileData {
  id: string
  email: string
  displayName: string
  phone: string | null
  avatarUrl: string | null
  roles: readonly string[]
  hasPassword: boolean
  twoFactorEnabled: boolean
  /** Membre du bureau sans 2FA : fonctions du club bloquées */
  twoFactorSetupRequired: boolean
  badges: readonly Badge[]
  preferences: DisplayPreferences | null
}

export class Profile {
  declare readonly id: string
  declare readonly email: string
  declare readonly displayName: string
  declare readonly phone: string | null
  declare readonly avatarUrl: string | null
  declare readonly roles: readonly string[]
  declare readonly hasPassword: boolean
  declare readonly twoFactorEnabled: boolean
  declare readonly twoFactorSetupRequired: boolean
  declare readonly badges: readonly Badge[]
  declare readonly preferences: DisplayPreferences | null

  constructor(data: ProfileData) {
    Object.assign(this, data)
  }

  static parse(value: unknown): Profile {
    const d = obj(value, 'profil')
    return new Profile({
      id: str(d.id),
      email: str(d.email),
      displayName: str(d.displayName),
      phone: optStr(d.phone),
      avatarUrl: optStr(d.avatarUrl),
      roles: strList(d.roles),
      hasPassword: bool(d.hasPassword),
      twoFactorEnabled: bool(d.twoFactorEnabled),
      twoFactorSetupRequired: bool(d.twoFactorSetupRequired),
      badges: parseBadges(d.badges),
      preferences: parsePreferences(d.preferences),
    })
  }

  get initials(): string {
    return initialsOf(this.displayName)
  }

  /** Accès à la trésorerie (trésorier, ou président / admin par hiérarchie) */
  get canManageTreasury(): boolean {
    return ['ROLE_TREASURER', 'ROLE_PRESIDENT', 'ROLE_ADMIN'].some((role) => this.roles.includes(role))
  }

  /** Organisation des événements (secrétariat, ou président / admin par hiérarchie) */
  get canOrganizeEvents(): boolean {
    return ['ROLE_SECRETARY', 'ROLE_PRESIDENT', 'ROLE_ADMIN'].some((role) => this.roles.includes(role))
  }

  /** Interface de la présidence (président, ou admin par hiérarchie) */
  get canAdminister(): boolean {
    return ['ROLE_PRESIDENT', 'ROLE_ADMIN'].some((role) => this.roles.includes(role))
  }

  /** Administrateur (sommet de la hiérarchie) : statistiques d'audience */
  get isAdmin(): boolean {
    return this.roles.includes('ROLE_ADMIN')
  }

  get isBoardMember(): boolean {
    return this.roles.some((role) => (BOARD_ROLES as readonly string[]).includes(role))
  }
}

export function initialsOf(name: string): string {
  const parts = name.split(/[\s._-]+/).filter(Boolean)
  const initials = parts.length > 1 ? `${parts[0]?.charAt(0) ?? ''}${parts[1]?.charAt(0) ?? ''}` : name.slice(0, 2)
  return initials.toUpperCase()
}
