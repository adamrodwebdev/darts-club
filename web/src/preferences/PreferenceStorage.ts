/** Stockage clé/valeur des préférences d'affichage (abstraction de localStorage). */
export interface PreferenceStorage {
  get(key: string): string | null
  set(key: string, value: string): void
}

/** localStorage peut être indisponible (navigation privée, cookies bloqués) : on l'ignore alors. */
export class BrowserPreferenceStorage implements PreferenceStorage {
  get(key: string): string | null {
    try {
      return window.localStorage.getItem(key)
    } catch {
      return null
    }
  }

  set(key: string, value: string): void {
    try {
      window.localStorage.setItem(key, value)
    } catch {
      // Préférence conservée pour la session seulement
    }
  }
}

export class MemoryPreferenceStorage implements PreferenceStorage {
  private readonly values = new Map<string, string>()

  get(key: string): string | null {
    return this.values.get(key) ?? null
  }

  set(key: string, value: string): void {
    this.values.set(key, value)
  }
}
