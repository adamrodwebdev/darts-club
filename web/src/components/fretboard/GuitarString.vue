<script setup lang="ts">
import { computed } from 'vue'
import { vReplay } from '@/motion/vReplay'

/**
 * Corde du manche : une section de l'accueil. En-tête façon tablature (six cordes, la
 * sienne allumée) et fil pincé à chaque arrivée à l'écran ; le contenu entre de côté, une
 * corde depuis la gauche, la suivante depuis la droite, comme un aller-retour de médiator,
 * et repart de même : tout se rejoue à chaque passage, en descendant comme en remontant.
 * id et nom accessible (aria-labelledby / aria-label) passent sur la <section>.
 */
const props = defineProps<{
  /** Numéro(s) de corde allumé(s), de 1 (aiguë) à 6 (grave) */
  strings: readonly number[]
  /** Repère affiché au-dessus de la section (« Corde 3 · Événements ») */
  label: string
}>()

const STRINGS = 6
const from = computed(() => ((props.strings[0] ?? 0) % 2 === 0 ? 'left' : 'right'))
</script>

<template>
  <section class="string" :class="`string--from-${from}`">
    <div v-replay class="string__head" aria-hidden="true">
      <span class="string__staff">
        <span v-for="n in STRINGS" :key="n" :class="{ 'is-on': strings.includes(n) }" />
      </span>
      <span class="string__label">{{ label }}</span>
      <span class="string__wire" />
    </div>
    <div class="string__body">
      <slot />
    </div>
  </section>
</template>

<style scoped>
.string {
  display: grid;
  gap: 1rem;
  min-width: 0;
}

.string__head {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.string__staff {
  display: grid;
  flex: none;
  gap: 3px;
  width: 1.75rem;
}

.string__staff span {
  height: 2px;
  border-radius: 2px;
  background: var(--color-border);
}

.string__staff .is-on {
  height: 3px;
  background: var(--color-accent);
}

.string__label {
  color: var(--color-link);
  font-size: 0.8rem;
  font-weight: 800;
  letter-spacing: 0.18em;
  text-transform: uppercase;
}

.string__wire {
  flex: 1;
  min-width: 2rem;
  height: 2px;
  background: var(--wire);
  transform-origin: left center;
}

.string__body {
  display: grid;
  gap: 1rem;
  min-width: 0;
}

/* Le fil est pincé à chaque arrivée de la corde à l'écran (v-replay sur l'en-tête) */
:root[data-motion='on'] .string__wire {
  animation: string-pluck 0.9s cubic-bezier(0.2, 0.8, 0.2, 1) both;
}

/*
 * Le contenu suit le défilement : il arrive de côté en pivotant et repart de même, donc se
 * rejoue dans les deux sens. Plages « entry » et « exit » : au repos dès que la corde est
 * entrée, même en bas de page sur un très grand écran.
 */
@supports (animation-timeline: view()) {
  .string {
    view-timeline: --string block;
  }

  :root[data-motion='on'] .string--from-left .string__body {
    animation: strum-from-left linear both;
    animation-timeline: --string;
    animation-range: entry 0% exit 100%;
  }

  :root[data-motion='on'] .string--from-right .string__body {
    animation: strum-from-right linear both;
    animation-timeline: --string;
    animation-range: entry 0% exit 100%;
  }
}

/* Tension avec un léger dépassement, puis la corde vibre et s'amortit */
@keyframes string-pluck {
  0% {
    opacity: 0.25;
    transform: scaleX(0.12) translateY(0);
  }

  45% {
    opacity: 1;
    transform: scaleX(1.03) translateY(0);
  }

  56% {
    transform: scaleX(1) translateY(-1.5px);
  }

  68% {
    transform: scaleX(1) translateY(1.1px);
  }

  80% {
    transform: scaleX(1) translateY(-0.6px);
  }

  91% {
    transform: scaleX(1) translateY(0.25px);
  }

  100% {
    opacity: 1;
    transform: scaleX(1) translateY(0);
  }
}

@keyframes strum-from-left {
  entry 0%,
  exit 100% {
    opacity: 0;
    transform: perspective(60rem) translateX(-2.5rem) rotateY(12deg);
  }

  entry 40%,
  exit 60% {
    opacity: 1;
    transform: none;
  }
}

@keyframes strum-from-right {
  entry 0%,
  exit 100% {
    opacity: 0;
    transform: perspective(60rem) translateX(2.5rem) rotateY(-12deg);
  }

  entry 40%,
  exit 60% {
    opacity: 1;
    transform: none;
  }
}
</style>
