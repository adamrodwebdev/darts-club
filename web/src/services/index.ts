import { ApiClient } from '@/api/ApiClient'
import { AuthService } from './AuthService'
import { MessagingService } from './MessagingService'
import { NotificationService } from './NotificationService'
import { ProfileService } from './ProfileService'
import { PushService } from './PushService'
import { RealtimeClient } from './RealtimeClient'

/** Composition des services de l'application (une instance chacun). */
const api = new ApiClient()

export const services = {
  auth: new AuthService(api),
  profile: new ProfileService(api),
  notifications: new NotificationService(api),
  messaging: new MessagingService(api),
  push: new PushService(api),
  realtime: new RealtimeClient(api),
}

export type Services = typeof services
