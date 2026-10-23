<script setup lang="ts">
import { computed } from 'vue'

/**
 * Corde du manche : une section de l'accueil. En-tête façon tablature (six cordes, la
 * sienne allumée) et fil qui se tend à l'arrivée ; le contenu entre de côté, une corde
 * depuis la gauche, la suivante depuis la droite, comme un aller-retour de médiator.
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
    <div class="string__head" aria-hidden="true">
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
  scroll-snap-align: start;
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

/*
 * Animations liées au défilement de la corde entière : le fil se tend, le contenu arrive
 * de côté en pivotant. Plages « entry » : tout est en place dès que la corde est entrée,
 * même en bas de page sur un très grand écran.
 */
@supports (animation-timeline: view()) {
  .string {
    view-timeline: --string block;
  }

  :root[data-motion='on'] .string__wire {
    animation: string-pluck linear both;
    animation-timeline: --string;
    animation-range: entry 0% entry 50%;
  }

  :root[data-motion='on'] .string--from-left .string__body {
    animation: strum-from-left linear both;
    animation-timeline: --string;
    animation-range: entry 0% entry 40%;
  }

  :root[data-motion='on'] .string--from-right .string__body {
    animation: strum-from-right linear both;
    animation-timeline: --string;
    animation-range: entry 0% entry 40%;
  }
}

@keyframes string-pluck {
  0% {
    opacity: 0.25;
    transform: scaleX(0.12);
  }

  70% {
    transform: scaleX(1.03);
  }

  100% {
    opacity: 1;
    transform: none;
  }
}

@keyframes strum-from-left {
  from {
    opacity: 0;
    transform: perspective(60rem) translateX(-2.5rem) rotateY(12deg);
  }

  to {
    opacity: 1;
    transform: none;
  }
}

@keyframes strum-from-right {
  from {
    opacity: 0;
    transform: perspective(60rem) translateX(2.5rem) rotateY(-12deg);
  }

  to {
    opacity: 1;
    transform: none;
  }
}
</style>
