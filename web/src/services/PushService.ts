import { i18n } from '@/i18n'
import type { ApiClient } from '@/api/ApiClient'
import { obj, optStr } from '@/models/guards'

/** Abonnement Web Push du navigateur (service worker /sw.js). */
export class PushService {
  constructor(
    private readonly api: ApiClient,
    private readonly scriptUrl = '/sw.js',
  ) {}

  isSupported(): boolean {
    return typeof window !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window
  }

  async isSubscribed(): Promise<boolean> {
    return (await this.existing()) !== null
  }

  async subscribe(): Promise<void> {
    const key = optStr(obj(await this.api.get('/api/push/public-key')).publicKey)
    if (key === null) {
      throw new Error(i18n.global.t('errors.pushUnavailable'))
    }

    if ((await Notification.requestPermission()) !== 'granted') {
      throw new Error(i18n.global.t('errors.pushDenied'))
    }

    const registration = await navigator.serviceWorker.register(this.scriptUrl)
    await navigator.serviceWorker.ready
    const subscription =
      (await registration.pushManager.getSubscription()) ??
      (await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: PushService.decodeKey(key),
      }))

    await this.api.post('/api/push/subscriptions', subscription.toJSON() as Record<string, unknown>)
  }

  async unsubscribe(): Promise<void> {
    const subscription = await this.existing()
    if (subscription === null) return

    await this.api.delete('/api/push/subscriptions', subscription.toJSON() as Record<string, unknown>)
    await subscription.unsubscribe()
  }

  private async existing(): Promise<PushSubscription | null> {
    if (!this.isSupported()) return null
    const registration = await navigator.serviceWorker.getRegistration(this.scriptUrl)
    return (await registration?.pushManager.getSubscription()) ?? null
  }

  /** Clé VAPID base64url → octets attendus par pushManager.subscribe(). */
  static decodeKey(base64url: string): Uint8Array<ArrayBuffer> {
    const padding = '='.repeat((4 - (base64url.length % 4)) % 4)
    const raw = atob((base64url + padding).replace(/-/g, '+').replace(/_/g, '/'))
    const bytes = new Uint8Array(new ArrayBuffer(raw.length))
    for (let i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i)
    return bytes
  }
}
