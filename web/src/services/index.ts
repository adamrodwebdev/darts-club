import { inject, type InjectionKey } from 'vue'
import { ApiClient } from '@/api/ApiClient'
import { systemClock, type Clock } from '@/time/Clock'
import { AdminService } from './AdminService'
import { AssociationProfileService } from './AssociationProfileService'
import { AuthService } from './AuthService'
import { EventAccountsService } from './EventAccountsService'
import { EventDocumentsService } from './EventDocumentsService'
import { EventService } from './EventService'
import { MessagingService } from './MessagingService'
import { NotificationService } from './NotificationService'
import { PasskeyService } from './PasskeyService'
import { ProfileService } from './ProfileService'
import { PublicEventService } from './PublicEventService'
import { PushService } from './PushService'
import { RealtimeClient } from './RealtimeClient'
import { TreasuryService } from './TreasuryService'

/** Dépendances remplaçables (tests) : client HTTP et horloge */
export interface ServiceDependencies {
  api?: ApiClient
  clock?: Clock
}

/**
 * Racine de composition : une instance de chaque service, créée au démarrage
 * (main.ts) puis fournie à l'application. Aucun service global : composants,
 * composables et stores les reçoivent par useServices().
 */
export function createServices({ api = new ApiClient(), clock = systemClock }: ServiceDependencies = {}) {
  return {
    clock,
    auth: new AuthService(api),
    passkeys: new PasskeyService(api),
    profile: new ProfileService(api),
    notifications: new NotificationService(api),
    messaging: new MessagingService(api),
    push: new PushService(api),
    realtime: new RealtimeClient(api),
    treasury: new TreasuryService(api),
    admin: new AdminService(api),
    events: new EventService(api),
    eventAccounts: new EventAccountsService(api),
    eventDocuments: new EventDocumentsService(api),
    associationProfile: new AssociationProfileService(api),
    publicEvents: new PublicEventService(api),
  }
}

export type Services = ReturnType<typeof createServices>

export const SERVICES: InjectionKey<Services> = Symbol('services')

/** Services de l'application (dans un setup, un composable ou un store). */
export function useServices(): Services {
  const services = inject(SERVICES)
  if (!services) throw new Error('Services absents : app.provide(SERVICES, createServices()) dans main.ts')
  return services
}
