import { bool, obj, optStr, str, strList } from './guards'

/** Champs de la fiche de l'association, tels que saisis (vide = inconnu) */
export interface AssociationDetails {
  officialName: string
  rnaNumber: string
  siret: string
  seatAddress: string
  presidentName: string
  secretaryName: string
  treasurerName: string
  insurerName: string
  insurancePolicy: string
}

/** Attestation d'assurance rangée dans la fiche (PDF de l'assureur) */
export interface InsuranceCertificate {
  /** « 2027-08-31 » */
  validUntil: string
  /** Valable aujourd'hui */
  valid: boolean
}

/** Fiche de l'association (bureau, jamais publiée) : reprise dans les documents des soirées. */
export class AssociationProfile {
  declare readonly details: AssociationDetails
  /** Ce qui manque pour des courriers complets (rna, seat, president, insurer) */
  declare readonly missing: readonly string[]
  declare readonly certificate: InsuranceCertificate | null
  declare readonly updatedAt: Date | null
  declare readonly updatedBy: string | null
  declare readonly mayEdit: boolean

  constructor(data: {
    details: AssociationDetails
    missing: readonly string[]
    certificate: InsuranceCertificate | null
    updatedAt: Date | null
    updatedBy: string | null
    mayEdit: boolean
  }) {
    Object.assign(this, data)
  }

  static parse(value: unknown): AssociationProfile {
    const d = obj(value, 'fiche')
    const text = (key: keyof AssociationDetails): string => optStr(d[key]) ?? ''
    const certificate = d.certificate === null || d.certificate === undefined ? null : obj(d.certificate, 'attestation')
    const updatedAt = optStr(d.updatedAt)
    return new AssociationProfile({
      details: {
        officialName: str(d.officialName),
        rnaNumber: text('rnaNumber'),
        siret: text('siret'),
        seatAddress: text('seatAddress'),
        presidentName: text('presidentName'),
        secretaryName: text('secretaryName'),
        treasurerName: text('treasurerName'),
        insurerName: text('insurerName'),
        insurancePolicy: text('insurancePolicy'),
      },
      missing: strList(d.missing),
      certificate: certificate === null ? null : { validUntil: str(certificate.validUntil), valid: bool(certificate.valid) },
      updatedAt: updatedAt === null ? null : new Date(updatedAt),
      updatedBy: optStr(d.updatedBy),
      mayEdit: bool(d.mayEdit),
    })
  }

  get complete(): boolean {
    return this.missing.length === 0
  }
}
