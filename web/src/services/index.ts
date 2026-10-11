import { ApiClient } from '@/api/ApiClient'
import { AdminService } from './AdminService'
import { AuthService } from './AuthService'
import { EventService } from './EventService'
import { MessagingService } from './MessagingService'
import { NotificationService } from './NotificationService'
import { ProfileService } from './ProfileService'
import { PublicEventService } from './PublicEventService'
import { PushService } from './PushService'
import { RealtimeClient } from './RealtimeClient'
import { TreasuryService } from './TreasuryService'

/** Composition des services de l'application (une instance chacun). */
const api = new ApiClient()

export const services = {
  auth: new AuthService(api),
  profile: new ProfileService(api),
  notifications: new NotificationService(api),
  messaging: new MessagingService(api),
  push: new PushService(api),
  realtime: new RealtimeClient(api),
  treasury: new TreasuryService(api),
  admin: new AdminService(api),
  events: new EventService(api),
  publicEvents: new PublicEventService(api),
}

export type Services = typeof services
