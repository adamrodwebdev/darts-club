/**
 * Données fixes de la page d'accueil. Les textes sont traduits dans
 * src/i18n/locales (section « landing »).
 */
export const LANDING = {
  name: 'Skull Darts 71',
  /** Écusson (fichier public, servi tel quel) */
  logo: '/logo.svg',
  facebook: 'https://www.facebook.com/p/Skull-Darts-71-100091328505582/',
  pillars: [
    { key: 'fun', icon: 'target' },
    { key: 'nights', icon: 'night' },
    { key: 'community', icon: 'crew' },
  ],
  /** Groupe proche du club */
  friendBand: {
    name: 'Blind to Deaf',
    url: 'https://www.facebook.com/share/1Fy1CPbgGh/',
  },
} as const
