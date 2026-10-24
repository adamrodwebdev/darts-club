/** Page à rejoindre après une étape de connexion : un chemin du site seulement (ni « //hôte », ni « /\hôte ») */
export function internalPath(value: unknown, fallback = '/compte'): string {
  return typeof value === 'string' && /^\/(?![/\\])/.test(value) ? value : fallback
}
