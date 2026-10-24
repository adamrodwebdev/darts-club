import type { ApiClient } from '@/api/ApiClient'
import { AssociationProfile, type AssociationDetails } from '@/models/AssociationProfile'

/** Fiche de l'association (bureau) : reprise dans les documents des soirées, jamais publiée. */
export class AssociationProfileService {
  constructor(private readonly api: ApiClient) {}

  async get(): Promise<AssociationProfile> {
    return AssociationProfile.parse(await this.api.get('/api/association-profile'))
  }

  async update(details: AssociationDetails): Promise<AssociationProfile> {
    return AssociationProfile.parse(await this.api.put('/api/association-profile', { ...details }))
  }

  /** Nouvelle attestation de l'assureur (PDF) et sa date de fin de validité « 2027-08-31 » */
  async replaceCertificate(file: File, validUntil: string): Promise<AssociationProfile> {
    const form = new FormData()
    form.append('file', file)
    form.append('validUntil', validUntil)
    return AssociationProfile.parse(await this.api.post('/api/association-profile/insurance', form))
  }

  /** Attestation téléchargée (jamais ouverte dans le site) */
  certificateUrl(): string {
    return '/api/association-profile/insurance'
  }
}
