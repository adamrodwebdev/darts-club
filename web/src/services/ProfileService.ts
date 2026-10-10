import type { ApiClient } from '@/api/ApiClient'
import { Profile } from '@/models/Profile'

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

  async deleteAccount(password: string): Promise<void> {
    await this.api.delete('/api/me', { confirmation: 'SUPPRIMER', password })
  }
}
