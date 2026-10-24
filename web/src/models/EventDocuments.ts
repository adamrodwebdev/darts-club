import { bool, InvalidResponseError, list, num, obj, optStr, str, strList, type Json } from './guards'

/** Démarches d'une soirée, dans l'ordre de la liste (identifiants des adresses du site) */
export const DOCUMENT_TYPES = ['buvette', 'salle', 'domaine-public', 'sacem', 'budget', 'billets', 'assurance', 'fermeture-tardive'] as const
export type DocumentType = (typeof DOCUMENT_TYPES)[number]

export const PROCEDURE_STATUSES = ['a-faire', 'prete', 'envoyee', 'recue', 'pas-necessaire'] as const
export type ProcedureStatus = (typeof PROCEDURE_STATUSES)[number]

export type FieldKind = 'text' | 'textarea' | 'checkbox' | 'choice'

/** Morceaux d'un document : courrier (expéditeur, destinataire…) ou fiche (titre, lignes) */
export const BLOCK_KINDS = ['expediteur', 'destinataire', 'lieu-date', 'objet', 'paragraphe', 'signature', 'titre', 'ligne', 'note'] as const
export type BlockKind = (typeof BLOCK_KINDS)[number]

export function isDocumentType(value: unknown): value is DocumentType {
  return typeof value === 'string' && (DOCUMENT_TYPES as readonly string[]).includes(value)
}

function oneOf<T extends string>(values: readonly T[], value: unknown, what: string): T {
  const text = str(value, what)
  const found = values.find((v) => v === text)
  if (found === undefined) throw new InvalidResponseError(what)
  return found
}

/** Soirée dont on prépare les démarches */
export interface EventRef {
  id: string
  title: string
  startsAt: Date
}

/** Démarche dans la liste : destinataire, date conseillée (jour, heure de Paris), avancement */
export interface ProcedureSummary {
  type: DocumentType
  status: ProcedureStatus
  recipient: string
  /** « 2026-10-30 » : à envoyer avant ce jour ; null sans délai */
  deadline: string | null
  sentOn: string | null
  receivedOn: string | null
  updatedAt: Date | null
  /** Le secrétariat a changé le document */
  edited: boolean
}

export interface DocumentField {
  key: string
  kind: FieldKind
  value: string | boolean
  /** Identifiants proposés (nature « choice ») */
  choices: readonly string[]
}

export interface DocumentBlock {
  id: string
  kind: BlockKind
  text: string
  /** Intitulé d'une ligne de fiche */
  label: string | null
  /** Texte réécrit par le secrétariat */
  rewritten: boolean
}

export interface OfficialLink {
  label: string
  url: string
}

/** Billets d'entrée à imprimer, numérotés de `first` à `first + count - 1` */
export interface TicketSeries {
  organizer: string
  event: string
  category: string
  price: string
  first: number
  count: number
}

function parseEvent(value: unknown): EventRef {
  const d = obj(value, 'soirée')
  return { id: str(d.id), title: str(d.title), startsAt: new Date(str(d.startsAt)) }
}

function parseSummary(d: Json): ProcedureSummary {
  const updatedAt = optStr(d.updatedAt)
  return {
    type: oneOf(DOCUMENT_TYPES, d.type, 'démarche'),
    status: oneOf(PROCEDURE_STATUSES, d.status, 'avancement'),
    recipient: str(d.recipient),
    deadline: optStr(d.deadline),
    sentOn: optStr(d.sentOn),
    receivedOn: optStr(d.receivedOn),
    updatedAt: updatedAt === null ? null : new Date(updatedAt),
    edited: bool(d.edited),
  }
}

function parseField(value: unknown): DocumentField {
  const d = obj(value, 'champ')
  const kind = oneOf(['text', 'textarea', 'checkbox', 'choice'] as const, d.kind, 'nature du champ')
  return {
    key: str(d.key),
    kind,
    value: kind === 'checkbox' ? bool(d.value) : str(d.value),
    choices: strList(d.choices),
  }
}

function parseBlock(value: unknown): DocumentBlock {
  const d = obj(value, 'passage')
  return { id: str(d.id), kind: oneOf(BLOCK_KINDS, d.kind, 'passage'), text: str(d.text), label: optStr(d.label), rewritten: bool(d.rewritten) }
}

function parseLink(value: unknown): OfficialLink {
  const d = obj(value, 'lien')
  return { label: str(d.label), url: str(d.url) }
}

function parseTickets(value: unknown): TicketSeries | null {
  if (value === null || value === undefined) return null
  const d = obj(value, 'billets')
  return { organizer: str(d.organizer), event: str(d.event), category: str(d.category), price: str(d.price), first: num(d.first), count: num(d.count) }
}

/** Jour « 2026-10-30 » → date à midi UTC : le même jour dans tous les fuseaux d'Europe */
export function dayDate(day: string): Date {
  return new Date(`${day}T12:00:00Z`)
}

/** Démarches d'une soirée et état de la fiche de l'association (bureau). */
export class EventProcedures {
  declare readonly event: EventRef
  declare readonly procedures: readonly ProcedureSummary[]
  /** Ce qui manque à la fiche de l'association (rna, seat, president, insurer) */
  declare readonly missing: readonly string[]
  declare readonly certificateValidUntil: string | null
  declare readonly certificateCoversEvent: boolean
  declare readonly mayEdit: boolean

  constructor(data: {
    event: EventRef
    procedures: readonly ProcedureSummary[]
    missing: readonly string[]
    certificateValidUntil: string | null
    certificateCoversEvent: boolean
    mayEdit: boolean
  }) {
    Object.assign(this, data)
  }

  static parse(value: unknown): EventProcedures {
    const d = obj(value, 'démarches')
    const profile = obj(d.profile, 'fiche')
    return new EventProcedures({
      event: parseEvent(d.event),
      procedures: list(d.procedures, (p) => parseSummary(obj(p, 'démarche'))),
      missing: strList(profile.missing),
      certificateValidUntil: optStr(profile.certificateValidUntil),
      certificateCoversEvent: bool(profile.certificateCoversEvent),
      mayEdit: bool(d.mayEdit),
    })
  }
}

/** Document rendu par l'API : champs, passages, liens officiels, billets. */
export class DocumentContent {
  declare readonly fields: readonly DocumentField[]
  declare readonly blocks: readonly DocumentBlock[]
  declare readonly links: readonly OfficialLink[]
  declare readonly tickets: TicketSeries | null

  constructor(data: { fields: readonly DocumentField[]; blocks: readonly DocumentBlock[]; links: readonly OfficialLink[]; tickets: TicketSeries | null }) {
    Object.assign(this, data)
  }

  static parse(value: unknown): DocumentContent {
    const d = obj(value, 'document')
    return new DocumentContent({
      fields: list(d.fields, parseField),
      blocks: list(d.blocks, parseBlock),
      links: list(d.links, parseLink),
      tickets: parseTickets(d.tickets),
    })
  }

  /** Passages du courrier ou de la fiche (les rappels au secrétariat restent hors de la page) */
  get sheet(): DocumentBlock[] {
    return this.blocks.filter((b) => b.kind !== 'note')
  }

  get notes(): DocumentBlock[] {
    return this.blocks.filter((b) => b.kind === 'note')
  }
}

/** Démarche ouverte : son avancement et son document. */
export class ProcedureDocument {
  declare readonly event: EventRef
  declare readonly summary: ProcedureSummary
  declare readonly updatedBy: string | null
  declare readonly content: DocumentContent
  declare readonly missing: readonly string[]
  declare readonly mayEdit: boolean

  constructor(data: { event: EventRef; summary: ProcedureSummary; updatedBy: string | null; content: DocumentContent; missing: readonly string[]; mayEdit: boolean }) {
    Object.assign(this, data)
  }

  static parse(value: unknown): ProcedureDocument {
    const d = obj(value, 'démarche')
    return new ProcedureDocument({
      event: parseEvent(d.event),
      summary: parseSummary(d),
      updatedBy: optStr(d.updatedBy),
      content: DocumentContent.parse(d),
      missing: strList(d.missing),
      mayEdit: bool(d.mayEdit),
    })
  }

  /** Une copie dont le document est remplacé (aperçu des changements en cours) */
  withContent(content: DocumentContent): ProcedureDocument {
    return new ProcedureDocument({ ...this, content })
  }
}
