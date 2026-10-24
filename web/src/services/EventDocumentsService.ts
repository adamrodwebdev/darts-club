import type { ApiClient } from '@/api/ApiClient'
import { DocumentContent, EventProcedures, ProcedureDocument, type DocumentType, type ProcedureStatus } from '@/models/EventDocuments'

/** Changements du secrétariat : valeurs des champs et passages réécrits, par identifiant */
export interface DocumentDraft {
  values: Record<string, string | boolean>
  rewrites: Record<string, string>
}

/**
 * Démarches des soirées (bureau). Les textes sont rendus par l'API (en français : ils partent vers
 * des administrations) ; l'écran les montre, les fait modifier et les imprime.
 */
export class EventDocumentsService {
  constructor(private readonly api: ApiClient) {}

  async list(eventId: string): Promise<EventProcedures> {
    return EventProcedures.parse(await this.api.get(this.base(eventId)))
  }

  async get(eventId: string, type: DocumentType): Promise<ProcedureDocument> {
    return ProcedureDocument.parse(await this.api.get(this.procedure(eventId, type)))
  }

  async save(eventId: string, type: DocumentType, draft: DocumentDraft): Promise<ProcedureDocument> {
    return ProcedureDocument.parse(await this.api.put(this.procedure(eventId, type), { ...draft }))
  }

  /** Document avec les changements en cours, sans les enregistrer */
  async preview(eventId: string, type: DocumentType, draft: DocumentDraft): Promise<DocumentContent> {
    return DocumentContent.parse(await this.api.post(`${this.procedure(eventId, type)}/preview`, { ...draft }))
  }

  async changeStatus(eventId: string, type: DocumentType, status: ProcedureStatus): Promise<ProcedureDocument> {
    return ProcedureDocument.parse(await this.api.put(`${this.procedure(eventId, type)}/status`, { status }))
  }

  private base(eventId: string): string {
    return `/api/events/${encodeURIComponent(eventId)}/procedures`
  }

  private procedure(eventId: string, type: DocumentType): string {
    return `${this.base(eventId)}/${encodeURIComponent(type)}`
  }
}
