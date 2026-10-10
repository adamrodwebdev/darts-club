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
    )
  }

  get initials(): string {
    return initialsOf(this.displayName)
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
