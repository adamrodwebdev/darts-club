<script setup lang="ts" generic="T">
import { useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { useReel } from '@/composables/useReel'

/**
 * Corde horizontale : des cartes à faire glisser (défilement natif avec points d'arrêt),
 * des repères façon incrustations de manche (un par carte, six au plus, sinon un compteur)
 * et des flèches pour la souris et le clavier. Variantes :
 * - cards : cartes calées à gauche, la bande déborde jusqu'au bord de l'écran ;
 * - deck : cartes plus larges (paroles) ;
 * - grid-wide : grille dès que la place suffit (plus rien à faire défiler) ;
 * - coverflow : carte centrale de face, voisines inclinées en profondeur.
 */
const props = withDefaults(
  defineProps<{
    items: readonly T[]
    /** Nom de la liste pour les lecteurs d'écran */
    label: string
    /** Nom de chaque carte (repères) */
    itemLabel: (item: T, index: number) => string
    /** Carte mise en avant par le contenu (partie en cours d'écoute…) */
    current?: number | null
    currentType?: 'true' | 'time'
    variant?: 'cards' | 'deck' | 'grid-wide' | 'coverflow'
  }>(),
  { current: null, currentType: 'true', variant: 'cards' },
)
defineSlots<{ default(scope: { item: T; index: number; active: boolean }): unknown }>()

/** Au-delà, les repères ne tiennent plus sur un téléphone : compteur « 3 / 11 » */
const MAX_FRETS = 6

const { t } = useI18n()
const track = useTemplateRef<HTMLElement>('track')
const reel = useReel(track, { count: () => props.items.length, align: props.variant === 'coverflow' ? 'center' : 'start' })
const { active, scrollable } = reel

defineExpose({ goTo: reel.goTo })
</script>

<template>
  <div class="fret-reel" :class="`fret-reel--${variant}`">
    <ul ref="track" class="fret-reel__track" role="list" :aria-label="label" :tabindex="scrollable ? 0 : undefined">
      <li
        v-for="(item, index) in items"
        :key="index"
        class="fret-reel__item"
        :class="{ 'is-active': index === active }"
        :data-reel-index="index"
        :aria-current="index === current ? currentType : undefined"
      >
        <slot :item="item" :index="index" :active="index === active" />
      </li>
    </ul>

    <div v-if="scrollable" class="fret-reel__nav">
      <button
        type="button"
        class="fret-reel__arrow"
        :aria-label="t('fretboard.previous')"
        :aria-disabled="active === 0 ? 'true' : undefined"
        @click="reel.previous()"
      >
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>
      </button>
      <ol v-if="items.length <= MAX_FRETS" class="fret-reel__frets" :aria-label="t('fretboard.frets', { label })">
        <li v-for="(item, index) in items" :key="index">
          <button
            type="button"
            class="fret"
            :class="{ 'is-active': index === active }"
            :aria-label="itemLabel(item, index)"
            :aria-current="index === active ? 'true' : undefined"
            @click="reel.goTo(index)"
          >
            <span class="fret__inlay" />
          </button>
        </li>
      </ol>
      <p v-else class="fret-reel__count">{{ t('fretboard.position', { current: active + 1, total: items.length }) }}</p>
      <button
        type="button"
        class="fret-reel__arrow"
        :aria-label="t('fretboard.next')"
        :aria-disabled="active === items.length - 1 ? 'true' : undefined"
        @click="reel.next()"
      >
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>
      </button>
    </div>
  </div>
</template>

<style scoped>
.fret-reel {
  container: fret-reel / inline-size;
  display: grid;
  gap: 0.25rem;
  min-width: 0;
}

.fret-reel__track {
  --reel-card: min(84%, 22rem);
  position: relative;
  display: flex;
  gap: 1rem;
  /* La bande va jusqu'au bord de l'écran : on voit qu'il reste des cartes à glisser */
  margin: 0 calc(-1 * var(--gutter));
  padding: 0.5rem var(--gutter) 1rem;
  scroll-padding-inline: var(--gutter);
  list-style: none;
  overflow-x: auto;
  overscroll-behavior-x: contain;
  scroll-snap-type: x mandatory;
  scrollbar-width: none;
}

.fret-reel__track::-webkit-scrollbar {
  display: none;
}

.fret-reel__track:focus-visible {
  outline: 2px solid var(--color-link);
  outline-offset: -2px;
}

.fret-reel__item {
  display: grid;
  flex: 0 0 var(--reel-card);
  min-width: 0;
  scroll-snap-align: start;
}

.fret-reel--deck .fret-reel__track {
  --reel-card: min(88%, 26rem);
}

/*
 * Longues bandes de texte (paroles) : seules les cartes proches de la partie visible sont mises
 * en page, les autres attendent qu'on glisse jusqu'à elles (elles restent dans le DOM, lues par
 * les lecteurs d'écran et la recherche). Réserve plus basse que toute carte : la bande garde la
 * hauteur des cartes affichées.
 */
.fret-reel--deck .fret-reel__item {
  content-visibility: auto;
  contain-intrinsic-block-size: auto 4rem;
}

/* Assez de place pour toutes les cartes : une grille, plus rien à faire glisser */
@container fret-reel (width >= 44rem) {
  .fret-reel--grid-wide .fret-reel__track {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
    margin: 0;
    padding: 0;
    overflow: visible;
  }
}

/*
 * Coverflow : la carte centrale de face, les voisines tournées vers elle, en retrait et
 * en partie cachées derrière elle. Scène limitée en largeur : même angle sur téléphone
 * et sur grand écran (l'angle suit la position de la carte dans la scène).
 */
.fret-reel--coverflow .fret-reel__track {
  --reel-card: min(62cqi, 18rem);
  width: min(100%, 52rem);
  margin: 0 auto;
  padding: 1.5rem calc((min(100cqi, 52rem) - var(--reel-card)) / 2) 2rem;
  gap: 0;
  scroll-padding-inline: 0;
  perspective: 70rem;
}

.fret-reel--coverflow .fret-reel__item {
  scroll-snap-align: center;
}

.fret-reel--coverflow .fret-reel__item + .fret-reel__item {
  margin-inline-start: -3rem;
}

/* Sans animation liée au défilement : cartes côte à côte, sans chevauchement */
@supports not (animation-timeline: view()) {
  .fret-reel--coverflow .fret-reel__item + .fret-reel__item {
    margin-inline-start: 1rem;
  }
}

:root[data-motion='off'] .fret-reel--coverflow .fret-reel__item + .fret-reel__item {
  margin-inline-start: 1rem;
}

@supports (animation-timeline: view()) {
  :root[data-motion='on'] .fret-reel--coverflow .fret-reel__item {
    animation: reel-coverflow linear both;
    animation-timeline: view(inline);
  }
}

/* Profondeur et ordre d'empilement suivent la position : la carte centrale passe devant */
@keyframes reel-coverflow {
  0% {
    z-index: 1;
    opacity: 0.35;
    transform: translateZ(-14rem) rotateY(65deg);
  }

  36% {
    z-index: 3;
    opacity: 0.8;
    transform: translateZ(-6rem) rotateY(42deg);
  }

  47%,
  53% {
    z-index: 5;
    opacity: 1;
    transform: none;
  }

  64% {
    z-index: 3;
    opacity: 0.8;
    transform: translateZ(-6rem) rotateY(-42deg);
  }

  100% {
    z-index: 1;
    opacity: 0.35;
    transform: translateZ(-14rem) rotateY(-65deg);
  }
}

.fret-reel__nav {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.25rem;
}

.fret-reel__arrow {
  display: grid;
  flex: none;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border: 1px solid var(--color-border);
  border-radius: 50%;
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
}

.fret-reel__arrow:hover {
  border-color: var(--color-accent);
}

.fret-reel__arrow[aria-disabled='true'] {
  opacity: 0.4;
  cursor: default;
}

.fret-reel__frets {
  display: flex;
  margin: 0;
  padding: 0;
  list-style: none;
}

/* Repère : une incrustation nacrée de manche, 44 px de cible pour le doigt */
.fret {
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: transparent;
  cursor: pointer;
}

.fret__inlay {
  width: 0.65rem;
  height: 0.65rem;
  border-radius: 50%;
  background: var(--inlay);
  box-shadow: 0 0 0 2px var(--color-border);
  transition:
    transform 0.2s ease,
    box-shadow 0.2s ease;
}

.fret.is-active .fret__inlay {
  transform: scale(1.35);
  box-shadow: 0 0 0 2px var(--color-accent);
}

.fret-reel__count {
  min-width: 4.5rem;
  margin: 0;
  color: var(--color-muted);
  font-variant-numeric: tabular-nums;
  text-align: center;
}
</style>
