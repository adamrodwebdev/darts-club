import type { Team } from '@/models/EventAccounts'

/** Noms des équipes de la soirée (carte des équipes et fenêtre d'ajout) */
export const TEAM_LABELS: Readonly<Record<Team, 'events.accounts.teams.names.accueil' | 'events.accounts.teams.names.bar' | 'events.accounts.teams.names.cuisine'>> = {
  accueil: 'events.accounts.teams.names.accueil',
  bar: 'events.accounts.teams.names.bar',
  cuisine: 'events.accounts.teams.names.cuisine',
}
