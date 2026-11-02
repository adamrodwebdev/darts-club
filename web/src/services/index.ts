import { inject, type InjectionKey } from 'vue'
import { ApiClient } from '@/api/ApiClient'
import { BrowserMediaProbe } from '@/media/MediaProbe'
import { systemClock, type Clock } from '@/time/Clock'
import { AdminService } from './AdminService'
import { AssociationProfileService } from './AssociationProfileService'
import { AuthService } from './AuthService'
import { EventAccountsService } from './EventAccountsService'
import { EventDocumentsService } from './EventDocumentsService'
import { EventService } from './EventService'
import { EveningDeskService } from './EveningDeskService'
import { randomIds, type IdGenerator } from './Ids'
import { MessagingService } from './MessagingService'
import { NotificationService } from './NotificationService'
import { PasskeyService } from './PasskeyService'
import { ProfileService } from './ProfileService'
import { PublicEventService } from './PublicEventService'
import { PushService } from './PushService'
import { RealtimeClient } from './RealtimeClient'
import { Chime } from './Chime'
import { OrderOutbox, SaleOutbox, type KeyValueStore } from './SaleOutbox'
import { TreasuryService } from './TreasuryService'
import { VideoUploadService } from './VideoUploadService'

/** Dépendances remplaçables (tests) : client HTTP, horloge, identifiants tirés sur l'appareil, stockage local */
export interface ServiceDependencies {
  api?: ApiClient
  clock?: Clock
  ids?: IdGenerator
  storage?: KeyValueStore | null
}

/** Stockage de l'appareil, quand le navigateur l'autorise (navigation privée : aucun) */
function deviceStorage(): KeyValueStore | null {
  try {
    return globalThis.localStorage ?? null
  } catch {
    return null
  }
}

/**
 * Racine de composition : une instance de chaque service, créée au démarrage
 * (main.ts) puis fournie à l'application. Aucun service global : composants,
 * composables et stores les reçoivent par useServices().
 */
export function createServices({ api = new ApiClient(), clock = systemClock, ids = randomIds, storage = deviceStorage() }: ServiceDependencies = {}) {
  return {
    clock,
    ids,
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
    eveningDesk: new EveningDeskService(api),
    saleOutbox: new SaleOutbox(storage),
    orderOutbox: new OrderOutbox(storage),
    chime: new Chime(),
    associationProfile: new AssociationProfileService(api),
    publicEvents: new PublicEventService(api),
    videoUploads: new VideoUploadService(api),
    media: new BrowserMediaProbe(),
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
