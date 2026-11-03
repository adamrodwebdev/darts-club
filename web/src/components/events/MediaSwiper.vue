<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import MediaActions from '@/components/events/MediaActions.vue'
import MediaSlide from '@/components/events/MediaSlide.vue'
import { useMouseSwipe } from '@/composables/useMouseSwipe'
import { useReel } from '@/composables/useReel'
import { GALLERY_LABELS, type GalleryItem, type GalleryRights } from '@/models/Gallery'
import { vReplay } from '@/motion/vReplay'
import { motionEnabled } from '@/preferences/MotionController'

/**
 * Largeur du cadre, déduite du CSS ci-dessous et de la page (72rem au plus, marges de 1 puis 2rem) :
 * bord à bord tant que le carrousel fait moins de 40rem, puis la page moins ses marges.
 */
const INLINE_SIZES = '(min-width: 72rem) 68rem, (min-width: 48rem) calc(100vw - 4rem), (min-width: 42rem) calc(100vw - 2rem), 100vw'
/** Plein écran : la fenêtre moins ses marges */
const FULLSCREEN_SIZES = 'calc(100vw - 1.5rem)'

/** Saut de plusieurs médias : celui visé arrive d'un coup et se pose */
const LANDING: Keyframe[] = [
  { opacity: 0.15, transform: 'scale(0.96)' },
  { opacity: 1, transform: 'none' },
]
const LANDING_TIMING: KeyframeAnimationOptions = { duration: 380, easing: 'cubic-bezier(0.2, 0.8, 0.2, 1)' }

/** Touches de la bande : où chacune mène */
const KEYS: Partial<Record<string, (current: number, last: number) => number>> = {
  ArrowRight: (current) => current + 1,
  ArrowLeft: (current) => current - 1,
  Home: () => 0,
  End: (_, last) => last,
}

/**
 * Carrousel des photos et vidéos d'une soirée : une à la fois, aussi grande que l'écran le permet,
 * au doigt, au pavé tactile, à la souris, aux flèches, aux vignettes ou au clavier. La suivante glisse
 * par-dessus celle à l'écran, qui s'enfonce dans l'ombre (animation liée au défilement : elle suit le
 * doigt ; sans elle, un simple glissement). Seules celle à l'écran et ses voisines chargent leur
 * grande image, une fois la galerie près de l'écran ; un saut de plusieurs ne charge rien entre les deux.
 */
const props = withDefaults(
  defineProps<{
    items: readonly GalleryItem[]
    /** Texte de chaque média pour les lecteurs d'écran (légende de l'auteur ou tirée de la soirée) */
    describe: (item: GalleryItem) => string
    rights: GalleryRights
    canManage: boolean
    /** Média montré à l'ouverture */
    start?: number
    /** Dans la fenêtre plein écran : flèches sur l'image, pas de bouton « plein écran » */
    fullscreen?: boolean
    /** Les mêmes médias sont ouverts en plein écran par-dessus : rien ne joue ici */
    suspended?: boolean
  }>(),
  { start: 0, fullscreen: false, suspended: false },
)
const emit = defineEmits<{ expand: [index: number]; move: [index: number]; changed: [] }>()
const { t } = useI18n()
const stage = useTemplateRef<HTMLElement>('stage')
const track = useTemplateRef<HTMLElement>('track')
const strip = useTemplateRef<HTMLElement>('strip')
const reel = useReel(track, { count: () => props.items.length, align: 'center' })
useMouseSwipe(track, reel)

/** Média à l'écran : celui d'ouverture, puis celui que la bande cale */
const index = ref(props.start)
/** La galerie est près de l'écran : les grandes images peuvent venir */
const seen = ref(false)
const announcement = ref('')
let sight: IntersectionObserver | null = null

const current = computed(() => props.items[index.value] ?? null)
const position = computed(() => (current.value === null ? '' : t(GALLERY_LABELS[current.value.kind].position, { index: index.value + 1, total: props.items.length })))

function itemLabel(item: GalleryItem, at: number): string {
  return t(GALLERY_LABELS[item.kind].item, { index: at + 1, total: props.items.length })
}

function go(target: number): void {
  const clamped = Math.min(Math.max(target, 0), props.items.length - 1)
  const far = Math.abs(clamped - index.value) > 1
  reel.goTo(clamped, { instant: far })
  if (far && motionEnabled()) track.value?.querySelector(`:scope > [data-reel-index="${clamped}"]`)?.animate(LANDING, LANDING_TIMING)
}

function onKey(event: KeyboardEvent): void {
  const target = KEYS[event.key]
  if (target === undefined) return
  event.preventDefault()
  go(target(index.value, props.items.length - 1))
}

/** La vignette du média à l'écran vient au milieu de la bande des vignettes (la page ne bouge pas) */
function centreThumb(at: number, smooth: boolean): void {
  const list = strip.value
  const thumb = list?.children.item(at)
  if (!list || !(thumb instanceof HTMLElement)) return
  list.scrollTo({ left: thumb.offsetLeft - (list.clientWidth - thumb.offsetWidth) / 2, behavior: smooth && motionEnabled() ? 'smooth' : 'auto' })
}

/** Toucher la photo à l'écran l'ouvre en grand ; le bouton de lecture et le lecteur gardent leurs clics */
function onTrackClick(event: MouseEvent): void {
  if (props.fullscreen || !(event.target instanceof Element) || event.target.closest('button, video') !== null) return
  const slide = event.target.closest<HTMLElement>('[data-reel-index]')
  if (slide !== null) emit('expand', Number(slide.dataset.reelIndex))
}

watch(reel.active, (at) => {
  index.value = at
})

watch(index, (at) => {
  emit('move', at)
  centreThumb(at, true)
  const item = props.items[at]
  if (item) announcement.value = t('events.gallery.announce', { item: itemLabel(item, at), name: item.media.uploader })
})

onMounted(() => {
  // Écouteur natif : le glissé à la souris avale le clic qui le termine avant qu'il arrive ici
  track.value?.addEventListener('click', onTrackClick)
  // Après le montage de la fenêtre qui la contient (ouverte par son propre montage, qui suit celui-ci)
  if (props.start > 0) {
    void nextTick(() => {
      reel.goTo(props.start, { instant: true })
      centreThumb(props.start, false)
    })
  }
  if (typeof IntersectionObserver === 'undefined' || stage.value === null) {
    seen.value = true
    return
  }
  sight = new IntersectionObserver(
    (entries) => {
      if (!entries.some((entry) => entry.isIntersecting)) return
      seen.value = true
      sight?.disconnect()
    },
    { rootMargin: '50% 0px' },
  )
  sight.observe(stage.value)
})

onBeforeUnmount(() => {
  track.value?.removeEventListener('click', onTrackClick)
  sight?.disconnect()
})

/** Retour du plein écran : le même média, sans défiler devant les autres */
defineExpose({ show: (at: number) => reel.goTo(at, { instant: true }) })
</script>

<template>
  <div class="swiper" :class="{ 'swiper--fullscreen': fullscreen }">
    <div ref="stage" v-replay class="swiper__stage">
      <!-- Motif « carrousel » de l'APG : un groupe nommé, une diapositive nommée par média -->
      <div
        ref="track"
        class="swiper__track"
        role="group"
        tabindex="0"
        :aria-roledescription="t('events.gallery.carouselRole')"
        :aria-label="t('events.gallery.title')"
        @keydown="onKey"
      >
        <div
          v-for="(item, at) in items"
          :key="item.key"
          class="swiper__slide"
          role="group"
          :aria-roledescription="t('events.gallery.slideRole')"
          :aria-label="itemLabel(item, at)"
          :aria-hidden="at === index ? undefined : 'true'"
          :inert="at !== index"
          :data-reel-index="at"
        >
          <MediaSlide
            :item="item"
            :description="describe(item)"
            :index="at"
            :total="items.length"
            :active="at === index && !suspended"
            :near="seen && Math.abs(at - index) <= 1"
            :sizes="fullscreen ? FULLSCREEN_SIZES : INLINE_SIZES"
          />
        </div>
      </div>
      <template v-if="fullscreen">
        <template v-if="items.length > 1">
          <button
            type="button"
            class="swiper__edge swiper__edge--previous"
            :aria-label="t('events.gallery.previous')"
            :aria-disabled="index === 0 ? 'true' : undefined"
            @click="go(index - 1)"
          >
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>
          </button>
          <button
            type="button"
            class="swiper__edge swiper__edge--next"
            :aria-label="t('events.gallery.next')"
            :aria-disabled="index === items.length - 1 ? 'true' : undefined"
            @click="go(index + 1)"
          >
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>
          </button>
        </template>
      </template>
      <template v-else>
        <p class="swiper__count" aria-hidden="true">{{ position }}</p>
        <button type="button" class="swiper__expand" :aria-label="t('events.gallery.fullscreen')" @click="emit('expand', index)">
          <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
        </button>
      </template>
    </div>

    <MediaActions v-if="current" :key="current.key" :item="current" :rights="rights" :can-manage="canManage" @changed="emit('changed')" />

    <div v-if="items.length > 1" class="swiper__nav">
      <button
        v-if="!fullscreen"
        type="button"
        class="swiper__arrow"
        :aria-label="t('events.gallery.previous')"
        :aria-disabled="index === 0 ? 'true' : undefined"
        @click="go(index - 1)"
      >
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>
      </button>
      <ol ref="strip" class="swiper__strip" :aria-label="t('events.gallery.strip')">
        <li v-for="(item, at) in items" :key="item.key">
          <button type="button" class="swiper__thumb" :aria-label="itemLabel(item, at)" :aria-current="at === index ? 'true' : undefined" @click="go(at)">
            <!-- Dimensions déclarées : aucun décalage ; AVIF ou WebP selon le navigateur -->
            <img v-if="item.preview.url" :src="item.preview.url" alt="" :width="item.preview.width" :height="item.preview.height" loading="lazy" decoding="async" draggable="false" />
            <svg v-if="item.kind === 'video'" class="swiper__thumb-play" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M8 5.5v13l11-6.5z" fill="currentColor" /></svg>
          </button>
        </li>
      </ol>
      <button
        v-if="!fullscreen"
        type="button"
        class="swiper__arrow"
        :aria-label="t('events.gallery.next')"
        :aria-disabled="index === items.length - 1 ? 'true' : undefined"
        @click="go(index + 1)"
      >
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>
      </button>
    </div>

    <p class="visually-hidden" aria-live="polite">{{ announcement }}</p>
  </div>
</template>

<style scoped>
.swiper {
  container: media-swiper / inline-size;
  display: grid;
  gap: 0.5rem;
  min-width: 0;
}

/*
 * Titre, filtres, photo, boutons et vignettes tiennent dans un écran de téléphone, sous l'en-tête
 * collant : 22rem pour l'en-tête, le titre, les filtres, les boutons et les vignettes (mesurés à
 * 390 × 844 et 393 × 659, e2e/event-gallery.spec.ts), et une marge.
 */
.swiper__stage {
  position: relative;
  isolation: isolate;
  block-size: clamp(15rem, 100svh - 22.5rem, 40rem);
  overflow: hidden;
  border: 1px solid var(--color-border);
  border-radius: calc(var(--radius) * 1.6);
  background: var(--media-stage);
  box-shadow: 0 1.5rem 3rem -1.5rem color-mix(in srgb, var(--color-accent) 70%, transparent);
}

/* Téléphone : la photo va d'un bord à l'autre de l'écran (la galerie est posée dans la page, à ses marges) */
@container media-swiper (width < 40rem) {
  .swiper:not(.swiper--fullscreen) > .swiper__stage {
    margin-inline: calc(-1 * var(--gutter));
    border-inline: 0;
    border-radius: 0;
  }
}

.swiper--fullscreen {
  grid-template-rows: minmax(0, 1fr) auto auto;
  gap: 0.5rem;
  height: 100%;
}

.swiper--fullscreen > .swiper__stage {
  block-size: auto;
  border: 0;
  border-radius: var(--radius);
  box-shadow: none;
}

.swiper__track {
  position: relative;
  display: flex;
  block-size: 100%;
  overflow-x: auto;
  overflow-y: hidden;
  overscroll-behavior-x: contain;
  scroll-snap-type: x mandatory;
  scrollbar-width: none;
  -webkit-user-select: none;
  user-select: none;
}

.swiper__track::-webkit-scrollbar {
  display: none;
}

.swiper__track:focus-visible {
  outline: 2px solid var(--color-link);
  outline-offset: -4px;
}

/* Un média par cran, même au coup de doigt rapide */
.swiper__slide {
  position: relative;
  flex: 0 0 100%;
  block-size: 100%;
  scroll-snap-align: center;
  scroll-snap-stop: always;
  view-timeline: --media-slide inline;
}

.swiper:not(.swiper--fullscreen) .swiper__slide {
  cursor: zoom-in;
}

/*
 * Pile : le média suivant glisse par-dessus celui à l'écran, qui recule, rapetisse et s'assombrit.
 * Liée au défilement, l'animation suit le doigt ; elle porte sur le cadre, jamais sur l'élément calé
 * (les points d'arrêt restent ceux de la mise en page).
 */
@supports (animation-timeline: view()) {
  :root[data-motion='on'] .swiper__slide > * {
    animation: media-stack linear both;
    animation-timeline: --media-slide;
  }

  /* La galerie monte en grandissant à son arrivée, comme le carrousel de l'accueil, et redescend en repartant */
  :root[data-motion='on'] .swiper:not(.swiper--fullscreen) > .swiper__stage {
    animation: stage-rise linear both;
    animation-timeline: view();
    animation-range: entry 10% exit 90%;
  }
}

@keyframes media-stack {
  0%,
  50% {
    opacity: 1;
    transform: none;
  }

  100% {
    opacity: 0.2;
    transform: translateX(72%) scale(0.84);
  }
}

@keyframes stage-rise {
  entry 10%,
  exit 90% {
    opacity: 0.3;
    transform: translateY(2rem) scale(0.94);
  }

  entry 90%,
  exit 10% {
    opacity: 1;
    transform: none;
  }
}

/* Reflet qui traverse la photo à chaque retour de la galerie à l'écran (v-replay) */
.swiper__stage::after {
  position: absolute;
  z-index: 2;
  inset: -10% auto -10% 0;
  width: 45%;
  background: linear-gradient(100deg, transparent, var(--media-glint) 45%, transparent);
  content: '';
  opacity: 0;
  pointer-events: none;
  transform: translateX(-130%) skewX(-14deg);
}

:root[data-motion='on'] .swiper:not(.swiper--fullscreen) > .swiper__stage::after {
  animation: stage-glint 1.5s cubic-bezier(0.3, 0.6, 0.2, 1) 0.3s both;
}

@keyframes stage-glint {
  0% {
    opacity: 0;
    transform: translateX(-130%) skewX(-14deg);
  }

  15%,
  85% {
    opacity: 1;
  }

  100% {
    opacity: 0;
    transform: translateX(330%) skewX(-14deg);
  }
}

.swiper__count {
  position: absolute;
  z-index: 1;
  inset: 0.75rem auto auto 0.75rem;
  margin: 0;
  padding: 0.2rem 0.55rem;
  border-radius: 0.45rem;
  background: var(--media-scrim);
  color: var(--on-media);
  font-size: 0.82rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  pointer-events: none;
}

.swiper__expand,
.swiper__edge {
  position: absolute;
  z-index: 1;
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: var(--media-scrim);
  color: var(--on-media);
  cursor: pointer;
}

.swiper__expand {
  inset: 0.5rem 0.5rem auto auto;
}

.swiper__edge {
  inset-block-start: 50%;
  translate: 0 -50%;
}

.swiper__edge--previous {
  inset-inline-start: 0.5rem;
}

.swiper__edge--next {
  inset-inline-end: 0.5rem;
}

.swiper__expand:hover,
.swiper__edge:hover:not([aria-disabled='true']) {
  background: color-mix(in srgb, var(--color-accent) 85%, var(--media-stage));
}

/* Flèches autour des vignettes, comme celles des autres bandes du site */
.swiper__nav {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: 0.25rem;
}

.swiper--fullscreen .swiper__nav {
  grid-template-columns: minmax(0, 1fr);
}

.swiper__arrow {
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  padding: 0;
  border: 1px solid var(--color-border);
  border-radius: 50%;
  background: var(--color-surface);
  color: var(--color-text);
  cursor: pointer;
}

.swiper__arrow:hover:not([aria-disabled='true']) {
  border-color: var(--color-accent);
}

.swiper__arrow[aria-disabled='true'],
.swiper__edge[aria-disabled='true'] {
  opacity: 0.4;
  cursor: default;
}

.swiper__strip {
  position: relative;
  display: flex;
  gap: 0.45rem;
  margin: 0;
  padding: 0.45rem 0.3rem;
  list-style: none;
  overflow-x: auto;
  overscroll-behavior-x: contain;
  scrollbar-width: none;
}

.swiper__strip::-webkit-scrollbar {
  display: none;
}

/* Peu de vignettes : centrées ; beaucoup : depuis le début, aucune coupée */
.swiper__strip > li:first-child {
  margin-inline-start: auto;
}

.swiper__strip > li:last-child {
  margin-inline-end: auto;
}

.swiper__thumb {
  position: relative;
  display: block;
  width: 3.25rem;
  height: 3.25rem;
  padding: 0;
  overflow: hidden;
  border: 0;
  border-radius: 0.55rem;
  background: var(--color-surface-2);
  cursor: pointer;
  opacity: 0.55;
  transform: scale(0.9);
  transition:
    transform 0.25s cubic-bezier(0.2, 0.8, 0.2, 1),
    opacity 0.25s ease,
    box-shadow 0.25s ease;
}

.swiper__thumb img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.swiper__thumb:hover {
  opacity: 0.85;
}

.swiper__thumb[aria-current='true'] {
  opacity: 1;
  transform: none;
  box-shadow:
    0 0 0 2px var(--color-accent),
    0 0.6rem 1.2rem -0.5rem color-mix(in srgb, var(--color-accent) 80%, transparent);
}

.swiper__thumb-play {
  position: absolute;
  inset: 0;
  margin: auto;
  color: var(--on-media);
  filter: drop-shadow(0 1px 2px var(--media-scrim));
}
</style>
