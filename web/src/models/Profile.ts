import { parseBadges, type Badge } from './Badge'
import { bool, obj, optStr, str, strList } from './guards'

export const BOARD_ROLES = ['ROLE_SECRETARY', 'ROLE_TREASURER', 'ROLE_PRESIDENT', 'ROLE_ADMIN'] as const

export class Profile {
  constructor(
    readonly id: string,
    readonly email: string,
    readonly displayName: string,
    readonly phone: string | null,
    readonly avatarUrl: string | null,
    readonly roles: readonly string[],
    readonly hasPassword: boolean,
    readonly twoFactorEnabled: boolean,
    /** Membre du bureau sans 2FA : fonctions du club bloquées */
    readonly twoFactorSetupRequired: boolean,
    readonly badges: readonly Badge[] = [],
  ) {}

  static parse(value: unknown): Profile {
    const d = obj(value, 'profil')
    return new Profile(
      str(d.id),
      str(d.email),
      str(d.displayName),
      optStr(d.phone),
      optStr(d.avatarUrl),
      strList(d.roles),
      bool(d.hasPassword),
      bool(d.twoFactorEnabled),
      bool(d.twoFactorSetupRequired),
      parseBadges(d.badges),
    )
  }

  get initials(): string {
    return initialsOf(this.displayName)
  }

  /** Accès à la trésorerie (trésorier, ou président / admin par hiérarchie) */
  get canManageTreasury(): boolean {
    return ['ROLE_TREASURER', 'ROLE_PRESIDENT', 'ROLE_ADMIN'].some((role) => this.roles.includes(role))
  }

  /** Interface de la présidence (président, ou admin par hiérarchie) */
  get canAdminister(): boolean {
    return ['ROLE_PRESIDENT', 'ROLE_ADMIN'].some((role) => this.roles.includes(role))
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
