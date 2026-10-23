<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { vReplay } from '@/motion/vReplay'
import { motionEnabled } from '@/preferences/MotionController'

/**
 * Main « cornes » du pied de page : elle surgit du bas et secoue la tête chaque fois
 * qu'on atteint le bas de la page (v-replay : cachée sous le bord tant qu'on est plus
 * haut). Animations coupées : elle reste immobile, visible. Décorative (aria-hidden),
 * elle ne capte aucun clic.
 */
const state = ref<'rocking' | 'still'>('rocking')

onMounted(() => {
  if (!motionEnabled()) state.value = 'still'
})
</script>

<template>
  <span v-replay="{ threshold: 0.9 }" class="rock-hand" :class="`rock-hand--${state}`" aria-hidden="true">
    <svg viewBox="0 0 100 130" width="72" height="94" focusable="false">
      <defs>
        <linearGradient id="rock-hand-metal" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0" class="rock-hand__stop rock-hand__stop--light" />
          <stop offset="0.55" class="rock-hand__stop rock-hand__stop--mid" />
          <stop offset="1" class="rock-hand__stop rock-hand__stop--dark" />
        </linearGradient>
      </defs>
      <g class="rock-hand__sparks" fill="none" stroke-width="3" stroke-linecap="round">
        <path d="M14 14 5 7M12 27 3 25M86 24 95 17M88 37 97 36" />
      </g>
      <g stroke-width="3" stroke-linejoin="round" class="rock-hand__body">
        <rect x="31" y="98" width="38" height="40" />
        <rect x="21" y="7" width="15" height="58" rx="7.5" transform="rotate(-5 28 62)" />
        <rect x="66" y="21" width="13" height="46" rx="6.5" transform="rotate(7 72 64)" />
        <rect x="20" y="50" width="60" height="56" rx="17" />
        <rect x="36" y="43" width="16" height="25" rx="8" />
        <rect x="51" y="45" width="15" height="23" rx="7.5" />
        <rect x="14" y="71" width="48" height="15" rx="7.5" transform="rotate(-22 20 79)" />
      </g>
      <g fill="none" stroke-width="2" stroke-linecap="round" class="rock-hand__lines">
        <path d="M40 52q4-3 8 0M55 54q3.5-2.5 7 0" />
      </g>
      <rect x="27" y="104" width="46" height="13" rx="3" class="rock-hand__band" />
      <g class="rock-hand__studs">
        <circle cx="35" cy="110.5" r="2.6" />
        <circle cx="45" cy="110.5" r="2.6" />
        <circle cx="55" cy="110.5" r="2.6" />
        <circle cx="65" cy="110.5" r="2.6" />
      </g>
    </svg>
  </span>
</template>

<style scoped>
/*
 * Coupée seulement en bas : la main monte depuis le bord du pied de page et peut pivoter
 * librement. overflow (et non clip-path, qui ne rogne que l'image) : cachée sous le bord,
 * elle n'allonge pas la page. La boîte monte de 6rem pour la laisser pivoter, sans rien décaler.
 */
.rock-hand {
  display: block;
  flex: none;
  box-sizing: content-box;
  width: 4.5rem;
  aspect-ratio: 100 / 130;
  margin-block-start: -6rem;
  padding-block-start: 6rem;
  overflow: visible clip;
  pointer-events: none;
}

.rock-hand svg {
  display: block;
  width: 100%;
  height: 100%;
  transform-origin: 50% 100%;
}

/* Couleurs en classes, pas en attribut style : la CSP du site (style-src 'self') les bloquerait */
.rock-hand__stop--light {
  stop-color: var(--hand-light);
}

.rock-hand__stop--mid {
  stop-color: var(--hand-mid);
}

.rock-hand__stop--dark {
  stop-color: var(--hand-dark);
}

.rock-hand__body {
  fill: url('#rock-hand-metal');
  stroke: var(--hand-line);
}

.rock-hand__lines {
  stroke: var(--hand-line);
  opacity: 0.55;
}

.rock-hand__band {
  fill: var(--hand-band);
  stroke: var(--hand-line);
  stroke-width: 2;
}

.rock-hand__studs {
  fill: var(--hand-light);
}

.rock-hand__sparks {
  stroke: var(--color-accent);
  opacity: 0;
}

:root[data-motion='on'] .rock-hand--rocking svg {
  animation:
    hand-rise 0.6s cubic-bezier(0.2, 1.5, 0.4, 1) both,
    hand-headbang 0.42s ease-in-out 0.6s 3;
}

:root[data-motion='on'] .rock-hand--rocking .rock-hand__sparks {
  animation: sparks-flash 0.42s ease-out 0.6s 3;
}

@keyframes hand-rise {
  from {
    translate: 0 105%;
  }

  to {
    translate: 0 0;
  }
}

@keyframes hand-headbang {
  0%,
  100% {
    rotate: 0deg;
  }

  30% {
    rotate: -11deg;
  }

  70% {
    rotate: 9deg;
  }
}

@keyframes sparks-flash {
  0%,
  100% {
    opacity: 0;
  }

  40% {
    opacity: 1;
  }
}
</style>
