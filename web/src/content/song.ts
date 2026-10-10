/**
 * « Triple Vingt » — hymne original du club : paroles et riff écrits pour le site.
 * Les paroles restent en français dans toutes les langues ; titres de parties et
 * indications de jeu sont traduits (src/i18n/locales, section « song »).
 */
export type SongSectionKey = 'intro' | 'verse1' | 'preChorus' | 'chorus' | 'verse2' | 'bridge' | 'solo' | 'finalChorus' | 'rallyingCry' | 'outro'
export type InstrumentalKey = 'intro' | 'solo' | 'outro'

export type SongSection =
  | { key: SongSectionKey; lines: string[]; instrumental?: undefined }
  /** Section instrumentale : indications de jeu (traduites) au lieu de paroles */
  | { key: InstrumentalKey; instrumental: true }

export const SONG = {
  title: 'Triple Vingt',
  tempo: 92,
  tuning: 'Drop C (C G C F A D)',
  sections: [
    {
      key: 'intro',
      instrumental: true,
    },
    {
      key: 'verse1',
      lines: [
        'La lumière du bar baisse doucement,',
        'Trois flèches dans la main, le cœur battant,',
        'Deux mètres trente-sept, la ligne au sol,',
        'Le monde se tait, plus rien ne vole.',
      ],
    },
    {
      key: 'preChorus',
      lines: ['Respire… vise… lâche…'],
    },
    {
      key: 'chorus',
      lines: [
        'Triple vingt ! Le métal plante la cible,',
        'Triple vingt ! Le bras devient fusible,',
        'Cent quatre-vingts, la salle rugit,',
        'On lève les verres jusqu’au bout de la nuit !',
      ],
    },
    {
      key: 'verse2',
      lines: [
        'J’ai visé le vingt, j’ai planté le un,',
        'Le liège se souvient de tous mes chagrins,',
        'La secrétaire note, le trésorier soupire :',
        '« T’as payé ta cotise ? » — je préfère partir.',
      ],
    },
    {
      key: 'chorus',
      lines: [
        'Triple vingt ! Le métal plante la cible,',
        'Triple vingt ! Le bras devient fusible,',
        'Cent quatre-vingts, la salle rugit,',
        'On lève les verres jusqu’au bout de la nuit !',
      ],
    },
    {
      key: 'bridge',
      lines: [
        '(parlé, sur l’arpège qui monte)',
        'Double sortie… il reste trente-deux.',
        'Double seize… le silence est religieux.',
        'Une flèche, un souffle, un dernier vœu…',
      ],
    },
    {
      key: 'solo',
      instrumental: true,
    },
    {
      key: 'finalChorus',
      lines: [
        'Triple vingt ! Le métal plante la cible,',
        'Triple vingt ! Le bras devient fusible,',
        'Cent quatre-vingts, la salle rugit,',
        'On lève les verres jusqu’au bout de la nuit !',
      ],
    },
    {
      key: 'rallyingCry',
      lines: ['Skull Darts ! Soixante et onze !', 'Skull Darts ! Jusqu’au dernier double !'],
    },
    {
      key: 'outro',
      instrumental: true,
    },
  ] satisfies SongSection[],
  /** Riff principal, tablature (corde grave en bas) */
  tab: [
    'D|----------------------------------|',
    'A|----------------------------------|',
    'F|----------------------------------|',
    'C|------------3-5-3-----------------|',
    'G|-------3-5--------5-3-------------|',
    'C|-0-0-0--------------------0-0-3-1-|',
  ],
} as const
