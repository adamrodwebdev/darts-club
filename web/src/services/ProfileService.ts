import { i18n } from '@/i18n'
import type { ApiClient } from '@/api/ApiClient'
import { list, obj, str } from '@/models/guards'
import { Profile } from '@/models/Profile'
import type { TwoFactorSetup } from './AuthService'

export interface ProfileChanges {
  displayName?: string
  /** Chaîne vide = suppression du numéro */
  phone?: string
}

export class ProfileService {
  constructor(private readonly api: ApiClient) {}

  async update(changes: ProfileChanges): Promise<Profile> {
    return Profile.parse(await this.api.patch('/api/me', { ...changes }))
  }

  async changePassword(currentPassword: string, newPassword: string): Promise<void> {
    await this.api.post('/api/me/password', { currentPassword, newPassword })
  }

  async uploadAvatar(file: File): Promise<Profile> {
    const form = new FormData()
    form.append('avatar', file)
    return Profile.parse(await this.api.post('/api/me/avatar', form))
  }

  async removeAvatar(): Promise<Profile> {
    return Profile.parse(await this.api.delete('/api/me/avatar'))
  }

  /** URL du téléchargement RGPD (navigation directe : le navigateur gère le fichier). */
  exportUrl(): string {
    return '/api/me/export'
  }

  async startTwoFactor(): Promise<TwoFactorSetup> {
    const d = obj(await this.api.post('/api/me/2fa/setup'))
    const qrCode = str(d.qrCode)
    if (!qrCode.startsWith('data:image/svg+xml;base64,')) throw new Error(i18n.global.t('errors.invalidQrCode'))
    return { secret: str(d.secret), uri: str(d.uri), qrCode }
  }

  /** @returns codes de secours, à afficher une seule fois */
  async confirmTwoFactor(code: string): Promise<string[]> {
    return list(obj(await this.api.post('/api/me/2fa/confirm', { code })).backupCodes, (c) => str(c))
  }

  async disableTwoFactor(code: string): Promise<void> {
    await this.api.delete('/api/me/2fa', { code })
  }

  async deleteAccount(password: string): Promise<void> {
    await this.api.delete('/api/me', { confirmation: 'SUPPRIMER', password })
  }
}
