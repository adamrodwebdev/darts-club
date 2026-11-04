import type { Clock } from '@/time/Clock'

/**
 * Un rechargement automatique au plus par minute et par onglet : un fichier vraiment absent
 * (déploiement cassé, réseau coupé) ne fait jamais boucler la page.
 */
export const RELOAD_SPACING_MS = 60_000

/** Préfixe des préférences, mais dans sessionStorage : propre à l'onglet, gardé d'un document à l'autre */
export const RELOAD_MEMORY_KEY = 'sd71.reloadedAt'

/** Chargement d'une page entière (abstraction de window.location) */
export interface PageLoader {
  /** Adresse de la page affichée : chemin, requête et ancre */
  current(): string
  /** Charge l'adresse comme un nouveau document : il vient de la version en ligne */
  load(path: string): void
}

/** Heure du dernier rechargement automatique de l'onglet */
export interface ReloadMemory {
  /** Instant en millisecondes, null si l'onglet ne s'est jamais rechargé ainsi */
  lastReload(): number | null
  rememberReload(at: number): void
}

/**
 * Onglet ouvert avant un déploiement : les fichiers de son code (noms à empreinte de Vite) ont
 * disparu du serveur, et chaque page, langue ou bibliothèque pas encore chargée échouait sans rien
 * dire. Le chargeur de Vite signale l'échec (`vite:preloadError`) ; la page visée est alors chargée
 * comme un nouveau document, qui vient de la version en ligne.
 */
export class StaleBuildReload {
  /** Page qu'une navigation en cours doit afficher */
  private target: string | null = null

  constructor(
    private readonly pages: PageLoader,
    private readonly memory: ReloadMemory,
    private readonly clock: Clock,
  ) {}

  /** Une navigation commence : si le code de sa page manque, c'est elle qui sera chargée */
  navigating(path: string): void {
    this.target = path
  }

  /** Page affichée : un code qui manquerait ensuite (langue, clé d'accès) recharge celle-ci */
  arrived(): void {
    this.target = null
  }

  /** Un fichier de code manque : charger la version en ligne de la page visée */
  codeMissing(): void {
    const now = this.clock.now().getTime()
    const last = this.memory.lastReload()
    if (last !== null && Math.abs(now - last) < RELOAD_SPACING_MS) return
    this.memory.rememberReload(now)
    // Onglet sans mémoire (stockage bloqué) : rien n'arrêterait une boucle, l'erreur suit son cours
    if (this.memory.lastReload() !== now) return
    this.pages.load(this.target ?? this.pages.current())
  }
}

/** Pages de la fenêtre */
export class LocationPages implements PageLoader {
  constructor(private readonly location: Pick<Location, 'pathname' | 'search' | 'hash' | 'assign'>) {}

  current(): string {
    return this.location.pathname + this.location.search + this.location.hash
  }

  load(path: string): void {
    this.location.assign(path)
  }
}

/** sessionStorage peut être indisponible (stockage bloqué) : l'onglet n'a alors aucune mémoire */
export class SessionReloadMemory implements ReloadMemory {
  constructor(private readonly storage: () => Pick<Storage, 'getItem' | 'setItem'>) {}

  lastReload(): number | null {
    try {
      const stored = this.storage().getItem(RELOAD_MEMORY_KEY)
      if (stored === null) return null
      const at = Number(stored)
      return Number.isFinite(at) ? at : null
    } catch {
      return null
    }
  }

  rememberReload(at: number): void {
    try {
      this.storage().setItem(RELOAD_MEMORY_KEY, String(at))
    } catch {
      // Onglet sans mémoire : StaleBuildReload ne recharge pas
    }
  }
}

/** Créée une fois au démarrage (main.ts) pour cette fenêtre, avant tout chargement de code */
export function watchForStaleBuild(view: Window, clock: Clock): StaleBuildReload {
  const memory = new SessionReloadMemory(() => view.sessionStorage)
  const reload = new StaleBuildReload(new LocationPages(view.location), memory, clock)
  view.addEventListener('vite:preloadError', () => reload.codeMissing())
  return reload
}
