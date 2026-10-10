/**
 * Contenus de la page d'accueil, regroupés pour être modifiés sans toucher
 * aux composants. Les influences ne citent que le nom des groupes et un lien
 * officiel : aucun logo, visuel ni extrait (respect du droit d'auteur et des marques).
 */
export const LANDING = {
  tagline: 'Fléchettes, fuzz et amitié.',
  intro:
    'Un club où l’on vise juste, où l’on joue fort et où l’on se retrouve autour de la cible. Débutants bienvenus, champions tolérés.',
  pillars: [
    { title: 'Tournois', text: 'Des compétitions internes et des déplacements pour défier les clubs voisins.', icon: 'trophy' },
    { title: 'Soirées', text: 'Entraînements, défis et parties improvisées, au son du stoner et du metal.', icon: 'night' },
    { title: 'Communauté', text: 'Une messagerie, un calendrier et des souvenirs partagés après chaque événement.', icon: 'crew' },
  ],
  influences: [
    { name: 'Monkey3', genre: 'Rock psychédélique instrumental · Suisse', url: 'https://monkey-3.bandcamp.com' },
    { name: '7 Weeks', genre: 'Stoner rock · Limoges', url: 'https://7weeks.bandcamp.com' },
    { name: 'My Sleeping Karma', genre: 'Psychédélique instrumental · Allemagne', url: 'https://mysleepingkarma.bandcamp.com' },
    { name: 'Alber Jupiter', genre: 'Krautrock spatial · Rennes', url: 'https://alberjupiter.bandcamp.com' },
    { name: 'Russian Circles', genre: 'Post-metal instrumental · Chicago', url: 'https://www.russiancircles.com' },
    { name: 'Baroness', genre: 'Sludge progressif · Savannah', url: 'https://www.yourbaroness.com' },
    { name: 'Elder', genre: 'Heavy psych · Berlin', url: 'https://beholdtheelder.bandcamp.com' },
    { name: 'Truckfighters', genre: 'Fuzz rock · Suède', url: 'https://teepeerecords.com/pages/truckfighters' },
    { name: 'Ultra Vomit', genre: 'Metal parodique · Nantes', url: 'https://fr.wikipedia.org/wiki/Ultra_Vomit' },
  ],
} as const
