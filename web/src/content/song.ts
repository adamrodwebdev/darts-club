/**
 * « Triple Vingt » — hymne original du club.
 * Paroles et riff écrits pour le site (aucun emprunt aux œuvres des groupes
 * cités en influences : seule l'ambiance stoner / psyché / post-metal inspire).
 */
export interface SongSection {
  label: string
  lines: string[]
  /** Section instrumentale : indication de jeu au lieu de paroles */
  instrumental?: boolean
}

export const SONG = {
  title: 'Triple Vingt',
  subtitle: 'Hymne du Darts Club',
  credits: 'Paroles et musique : Darts Club. Libre de droits pour le club.',
  tempo: 92,
  tuning: 'Drop C (C G C F A D)',
  mood: 'Stoner groove, fuzz chaud, montée post-metal, refrain à chanter le verre levé',
  sections: [
    {
      label: 'Intro',
      instrumental: true,
      lines: ['Basse fuzz seule sur le riff, 4 mesures', 'Entrée batterie : charleston ouvert, puis tout le groupe'],
    },
    {
      label: 'Couplet 1',
      lines: [
        'La lumière du bar baisse doucement,',
        'Trois flèches dans la main, le cœur battant,',
        'Deux mètres trente-sept, la ligne au sol,',
        'Le monde se tait, plus rien ne vole.',
      ],
    },
    {
      label: 'Pré-refrain',
      lines: ['Respire… vise… lâche…'],
    },
    {
      label: 'Refrain',
      lines: [
        'Triple vingt ! Le métal plante la cible,',
        'Triple vingt ! Le bras devient fusible,',
        'Cent quatre-vingts, la salle rugit,',
        'On lève les verres jusqu’au bout de la nuit !',
      ],
    },
    {
      label: 'Couplet 2',
      lines: [
        'J’ai visé le vingt, j’ai planté le un,',
        'Le liège se souvient de tous mes chagrins,',
        'La secrétaire note, le trésorier soupire :',
        '« T’as payé ta cotise ? » — je préfère partir.',
      ],
    },
    {
      label: 'Refrain',
      lines: [
        'Triple vingt ! Le métal plante la cible,',
        'Triple vingt ! Le bras devient fusible,',
        'Cent quatre-vingts, la salle rugit,',
        'On lève les verres jusqu’au bout de la nuit !',
      ],
    },
    {
      label: 'Pont',
      lines: [
        '(parlé, sur l’arpège qui monte)',
        'Double sortie… il reste trente-deux.',
        'Double seize… le silence est religieux.',
        'Une flèche, un souffle, un dernier vœu…',
      ],
    },
    {
      label: 'Solo',
      instrumental: true,
      lines: ['Wah grand ouvert sur la gamme blues de do', 'Montée post-metal : la batterie double, tout le monde à l’unisson'],
    },
    {
      label: 'Refrain final ×2',
      lines: [
        'Triple vingt ! Le métal plante la cible,',
        'Triple vingt ! Le bras devient fusible,',
        'Cent quatre-vingts, la salle rugit,',
        'On lève les verres jusqu’au bout de la nuit !',
      ],
    },
    {
      label: 'Outro',
      instrumental: true,
      lines: ['Larsen tenu sur le do grave', 'Dernier coup de grosse caisse… et le bruit d’une fléchette dans le mille'],
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
