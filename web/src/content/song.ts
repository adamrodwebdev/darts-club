/**
 * « Triple Vingt » — hymne du club : paroles écrites pour le site, enregistrement
 * dans public/audio. Les paroles restent en français dans toutes les langues ; titres
 * de parties et indications de jeu sont traduits (src/i18n/locales, section « song »).
 * Chaque partie indique où elle commence dans l'enregistrement (en secondes).
 */
export type SongSectionKey = 'intro' | 'verse1' | 'preChorus' | 'chorus' | 'verse2' | 'bridge' | 'solo' | 'finalChorus' | 'rallyingCry' | 'outro'
export type InstrumentalKey = 'intro' | 'solo' | 'outro'

export type SongSection =
  | { key: SongSectionKey; start: number; lines: string[]; instrumental?: undefined }
  /** Section instrumentale : indications de jeu (traduites) au lieu de paroles */
  | { key: InstrumentalKey; start: number; instrumental: true }

export const SONG = {
  title: 'Triple Vingt',
  /** Durée de l'enregistrement, en secondes */
  duration: 355,
  /** Opus (léger) d'abord, MP3 pour les navigateurs qui ne lisent pas WebM */
  recordings: [
    { src: '/audio/triple-vingt.webm', type: 'audio/webm; codecs=opus' },
    { src: '/audio/triple-vingt.mp3', type: 'audio/mpeg' },
  ],
  sections: [
    {
      key: 'intro',
      start: 0,
      instrumental: true,
    },
    {
      key: 'verse1',
      start: 69,
      lines: [
        'La lumière du bar baisse doucement,',
        'Trois flèches dans la main, le cœur battant,',
        'Deux mètres trente-sept, la ligne au sol,',
        'Le monde se tait, plus rien ne vole.',
      ],
    },
    {
      key: 'preChorus',
      start: 95,
      lines: ['Respire… vise… lâche…'],
    },
    {
      key: 'chorus',
      start: 100,
      lines: [
        'Triple vingt ! Le métal plante la cible,',
        'Triple vingt ! Le bras devient fusible,',
        'Cent quatre-vingts, la salle rugit,',
        'On lève les verres jusqu’au bout de la nuit !',
      ],
    },
    {
      key: 'verse2',
      start: 138,
      lines: [
        'J’ai visé le vingt, j’ai planté le un,',
        'Le liège se souvient de tous mes chagrins,',
        'La secrétaire note, le trésorier soupire :',
        '« T’as payé ta cotise ? » — je préfère partir.',
      ],
    },
    {
      key: 'chorus',
      start: 150,
      lines: [
        'Triple vingt ! Le métal plante la cible,',
        'Triple vingt ! Le bras devient fusible,',
        'Cent quatre-vingts, la salle rugit,',
        'On lève les verres jusqu’au bout de la nuit !',
      ],
    },
    {
      key: 'bridge',
      start: 200,
      lines: [
        '(parlé, sur l’arpège qui monte)',
        'Double sortie… il reste trente-deux.',
        'Double seize… le silence est religieux.',
        'Une flèche, un souffle, un dernier vœu…',
      ],
    },
    {
      key: 'solo',
      start: 222,
      instrumental: true,
    },
    {
      key: 'finalChorus',
      start: 280,
      lines: [
        'Triple vingt ! Le métal plante la cible,',
        'Triple vingt ! Le bras devient fusible,',
        'Cent quatre-vingts, la salle rugit,',
        'On lève les verres jusqu’au bout de la nuit !',
      ],
    },
    {
      key: 'rallyingCry',
      start: 307,
      lines: ['Skull Darts ! Soixante et onze !', 'Skull Darts ! Jusqu’au dernier double !'],
    },
    {
      key: 'outro',
      start: 332,
      instrumental: true,
    },
  ] satisfies SongSection[],
} as const
