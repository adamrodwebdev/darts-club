<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatClock } from '@/i18n/format'

/** Commandes du lecteur de l'hymne (lecture, position) ; l'élément <audio> appartient au parent. */
const props = defineProps<{ playing: boolean; time: number; duration: number; failed: boolean }>()
const emit = defineEmits<{ toggle: []; seek: [seconds: number] }>()
const { t } = useI18n()

const clock = computed(() => ({ time: formatClock(props.time), duration: formatClock(props.duration) }))

function onSeek(event: Event): void {
  emit('seek', Number((event.target as HTMLInputElement).value))
}
</script>

<template>
  <div class="player">
    <div class="player__controls">
      <button type="button" class="play" @click="emit('toggle')">
        <svg v-if="!playing" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M7 4l13 8-13 8z" fill="currentColor" /></svg>
        <svg v-else viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M6 4h4v16H6zM14 4h4v16h-4z" fill="currentColor" /></svg>
        {{ playing ? t('song.pause') : t('song.play') }}
      </button>
      <div class="meter" :class="{ 'meter--playing': playing }" aria-hidden="true">
        <span v-for="i in 12" :key="i" :style="{ '--i': i }" />
      </div>
    </div>
    <div class="timeline">
      <input
        type="range"
        min="0"
        :max="duration"
        step="1"
        :value="Math.floor(time)"
        :aria-label="t('song.position')"
        :aria-valuetext="t('song.positionValue', clock)"
        @input="onSeek"
      >
      <span class="clock" aria-hidden="true">{{ t('song.clock', clock) }}</span>
    </div>
    <p v-if="failed" class="error" role="alert">{{ t('song.unavailable') }}</p>
    <p class="muted small">{{ t('song.playerNote', { duration: clock.duration }) }}</p>
  </div>
</template>

<style scoped>
.player {
  display: grid;
  gap: 0.75rem;
}

.player__controls {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 1rem;
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
  transform: scaleY(0.08);
  transform-origin: bottom;
}

/* Égaliseur décoratif ; coupé avec les animations (main.css) */
.meter--playing span {
  animation: meter-bounce calc(0.5s + var(--i) * 0.045s) ease-in-out calc(var(--i) * -0.13s) infinite alternate;
}

@keyframes meter-bounce {
  from {
    transform: scaleY(0.15);
  }

  to {
    transform: scaleY(1);
  }
}

.timeline {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

/* Piste haute de 44 px : cible tactile confortable */
.timeline input {
  flex: 1;
  min-width: 0;
  height: 2.75rem;
  margin: 0;
  accent-color: var(--color-accent);
  cursor: pointer;
}

.clock {
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
}

.error {
  margin: 0;
  color: var(--color-danger);
}

.small {
  margin: 0;
  font-size: 0.8rem;
}
</style>
