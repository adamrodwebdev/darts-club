/** Contenus de la page d'accueil, regroupés pour être modifiés sans toucher aux composants. */
export const LANDING = {
  name: 'Skull Darts 71',
  /** Écusson (fichier public, servi tel quel) */
  logo: '/logo.svg',
  facebook: 'https://www.facebook.com/p/Skull-Darts-71-100091328505582/',
  tagline: 'Fléchettes, fuzz et amitié.',
  intro:
    'Un club où l’on vise juste, où l’on joue fort et où l’on se retrouve autour de la cible. Débutants bienvenus, champions tolérés.',
  pillars: [
    { title: 'Pour le plaisir', text: 'Une association récréative : on joue pour s’amuser, débutants comme habitués, sans pression ni classement.', icon: 'target' },
    { title: 'Soirées', text: 'Entraînements, défis et parties improvisées, au son du stoner et du metal.', icon: 'night' },
    { title: 'Communauté', text: 'Une messagerie, un calendrier et des souvenirs partagés après chaque événement.', icon: 'crew' },
  ],
  /** Groupe proche du club */
  friendBand: {
    name: 'Blind to Deaf',
    text: 'Un groupe proche du club. Va découvrir leur musique et suivre leurs prochaines dates.',
    url: 'https://www.facebook.com/share/1Fy1CPbgGh/',
    linkLabel: 'Leur page Facebook',
  },
} as const
