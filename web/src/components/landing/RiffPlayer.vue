<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue'
import type { RiffSynth } from '@/audio/RiffSynth'

/** Joue le riff synthétisé ; le moteur audio n'est chargé qu'au premier clic. */
const playing = ref(false)
const bars = ref<number[]>(Array(12).fill(0.08))
const unsupported = ref(false)
let synth: RiffSynth | null = null
let frame = 0

function animate(): void {
  if (!synth) return
  const level = synth.level()
  bars.value = bars.value.map((_, i) => Math.max(0.08, Math.min(1, level * (0.6 + 0.4 * Math.sin(Date.now() / 90 + i)))))
  frame = requestAnimationFrame(animate)
}

async function toggle(): Promise<void> {
  if (playing.value) {
    synth?.stop()
    cancelAnimationFrame(frame)
    bars.value = bars.value.map(() => 0.08)
    playing.value = false
    return
  }

  if (typeof AudioContext === 'undefined') {
    unsupported.value = true
    return
  }

  const { RiffSynth } = await import('@/audio/RiffSynth')
  synth ??= new RiffSynth()
  await synth.start()
  playing.value = true
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) animate()
}

onBeforeUnmount(() => {
  cancelAnimationFrame(frame)
  void synth?.dispose()
})
</script>

<template>
  <div class="player">
    <button type="button" class="play" :aria-pressed="playing" @click="toggle">
      <svg v-if="!playing" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M7 4l13 8-13 8z" fill="currentColor" /></svg>
      <svg v-else viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M6 4h4v16H6zM14 4h4v16h-4z" fill="currentColor" /></svg>
      {{ playing ? 'Couper le riff' : 'Écouter le riff' }}
    </button>
    <div class="meter" aria-hidden="true">
      <span v-for="(height, i) in bars" :key="i" :style="{ transform: `scaleY(${height})` }" />
    </div>
    <p v-if="unsupported" class="muted" role="status">Ton navigateur ne gère pas la synthèse audio.</p>
    <p class="muted small">Riff joué en direct par ton navigateur (synthèse Web Audio, aucun fichier téléchargé). Attention au volume !</p>
  </div>
</template>

<style scoped>
.player {
  display: grid;
  gap: 0.75rem;
  justify-items: start;
}

.play {
  display: inline-flex;
  align-items: center;
  gap: 0.6rem;
  min-height: 3rem;
  padding: 0 1.4rem;
  border: 0;
  border-radius: 999px;
  background: var(--color-accent);
  color: var(--color-on-accent);
  font: inherit;
  font-weight: 800;
  cursor: pointer;
}

.play:hover {
  background: var(--color-accent-hover);
}

.meter {
  display: flex;
  align-items: flex-end;
  gap: 0.3rem;
  height: 2.5rem;
}

.meter span {
  width: 0.45rem;
  height: 100%;
  border-radius: 2px;
  background: linear-gradient(to top, var(--color-accent), var(--badge-gold));
  transform-origin: bottom;
  transition: transform 0.08s linear;
}

.small {
  margin: 0;
  font-size: 0.8rem;
}
</style>
