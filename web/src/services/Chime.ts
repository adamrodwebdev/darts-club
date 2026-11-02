/**
 * Signal des écrans de soirée (commande prête au bar, nouvelle commande en cuisine) : deux notes
 * courtes jouées par le navigateur, sans fichier à télécharger, et une vibration sur téléphone.
 * Le son ne part qu'après un geste sur la page (règle des navigateurs) : `unlock()` au premier appui.
 */
export class Chime {
  private context: AudioContext | null = null

  unlock(): void {
    try {
      this.context ??= new AudioContext()
      void this.context.resume()
    } catch {
      // Pas de son sur ce navigateur : la vibration et l'écran suffisent
    }
  }

  play(): void {
    navigator.vibrate?.([120, 60, 120])
    const context = this.context
    if (context === null || context.state !== 'running') return
    const start = context.currentTime
    for (const [offset, frequency] of [
      [0, 880],
      [0.18, 1320],
    ] as const) {
      const tone = context.createOscillator()
      const volume = context.createGain()
      tone.frequency.value = frequency
      volume.gain.setValueAtTime(0.0001, start + offset)
      volume.gain.exponentialRampToValueAtTime(0.25, start + offset + 0.02)
      volume.gain.exponentialRampToValueAtTime(0.0001, start + offset + 0.16)
      tone.connect(volume).connect(context.destination)
      tone.start(start + offset)
      tone.stop(start + offset + 0.18)
    }
  }
}
