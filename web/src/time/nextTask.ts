/** Planificateur de tâches des navigateurs qui le proposent : `scheduler.yield()` */
interface YieldingScheduler {
  yield(): Promise<void>
}

/**
 * Promesse tenue à la tâche suivante, sans attendre d'image : le travail qui suit ne s'ajoute pas
 * à la tâche en cours (Lighthouse : temps de blocage). `scheduler.yield()` quand le navigateur l'a
 * (la suite passe avant les tâches arrivées entre-temps), sinon un message à soi-même, sans le délai
 * minimal de setTimeout.
 */
export function nextTask(): Promise<void> {
  const scheduler = (globalThis as { scheduler?: Partial<YieldingScheduler> }).scheduler
  if (typeof scheduler?.yield === 'function') return scheduler.yield()
  return new Promise((resolve) => {
    const channel = new MessageChannel()
    channel.port1.onmessage = () => {
      channel.port1.close()
      resolve()
    }
    channel.port2.postMessage(null)
  })
}
