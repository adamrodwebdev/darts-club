/** Textes et QR code d'une affiche (site ou soirée publique) */
export interface QrPosterContent {
  /** Ligne au-dessus du titre (nom de la soirée), absente pour l'affiche du site */
  eyebrow: string | null
  heading: string
  subheading: string
  /** Grande ligne sous le QR code, puis la petite */
  primary: string
  secondary: string
  footer: string
  qrSrc: string
  qrAlt: string
}
