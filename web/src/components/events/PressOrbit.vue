<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import type { PressClipping } from '@/models/PressClipping'
import { frontCard, orbitSlots, orbitStep, settleCard, turnTowards, wrapIndex } from '@/motion/orbit'
import { usePreferencesStore } from '@/stores/preferences'

/** L'anneau à partir de trois coupures (deux se feraient face) et jusqu'à douze (au-delà, la grille se lit mieux) */
const RING_MIN = 3
const RING_MAX = 12
/** Largeur du bloc (rem) à partir de laquelle l'anneau a la place de tourner */
const ROOM_REM = 20
/** Écran assez haut pour l'anneau et ses flèches : un téléphone couché prend la grille */
const TALL_QUERY = '(min-height: 30rem)'
/** Déplacement (px) à partir duquel un appui devient un glissé */
const DRAG_START = 6
/** Part d'une carte qu'un glissé doit parcourir pour passer à la voisine, même sans l'amener devant */
const SWIPE_SHARE = 0.2
/** Largeur d'une carte quand la page ne la mesure pas (pas de mise en page) */
const FALLBACK_CARD_PX = 160
/** Largeur des vignettes, déduite du CSS : 14,5rem au plus dans l'anneau ; dans la grille, la page entière au téléphone, 20rem au plus dès deux colonnes */
const RING_SIZES = '14.5rem'
const GRID_SIZES = '(min-width: 40rem) 20rem, calc(100vw - 2rem)'

/** Touches de l'anneau : la carte que chacune amène devant (l'anneau boucle) */
const KEYS = new Map<string, (front: number, last: number) => number>([
  ['ArrowRight', (front) => front + 1],
  ['ArrowLeft', (front) => front - 1],
  ['Home', () => 0],
  ['End', (_, last) => last],
])

/**
 * « Dans la presse » : les coupures en orbite 3D autour du titre, comme dans la démo Plumb, mais
 * tournées à la demande (flèches, glissé au doigt ou à la souris, ← et → au clavier), jamais par le
 * défilement : la page défile toujours librement par-dessus. Une carte qui prend le focus vient
 * devant ; toucher une carte l'ouvre en grand (`open`). Sans animation, dans un bloc trop étroit, sur
 * un écran trop bas, ou avec trop peu ou trop de coupures : une grille des mêmes cartes, entières.
 */
const props = defineProps<{
  clippings: readonly PressClipping[]
  /** Identifiant du titre « Dans la presse », qui nomme aussi la section qui le contient */
  titleId: string
}>()
const emit = defineEmits<{ open: [index: number] }>()
const { t } = useI18n()
const preferences = usePreferencesStore()
const root = useTemplateRef<HTMLElement>('root')
const stage = useTemplateRef<HTMLElement>('stage')

const roomy = ref(false)
const tall = ref(true)
/** Tour de l'anneau, en degrés : la carte devant est celle qu'il amène face au lecteur */
const turn = ref(0)
const dragging = ref(false)
const announcement = ref('')

const count = computed(() => props.clippings.length)
const ring = computed(() => preferences.appliedMotion === 'on' && roomy.value && tall.value && count.value >= RING_MIN && count.value <= RING_MAX)
const slots = computed(() => orbitSlots(count.value))
const front = computed(() => frontCard(turn.value, count.value))
const status = computed(() => statusOf(front.value))

function statusOf(index: number): string {
  const outlet = props.clippings[index]?.details.outlet ?? null
  const position = { index: index + 1, total: count.value }
  return outlet === null ? t('events.press.statusBare', position) : t('events.press.status', { ...position, outlet })
}

function captionId(at: number): string {
  return `${props.titleId}-card-${at}`
}

function hasCaption(clipping: PressClipping): boolean {
  const { outlet, publishedOn, title, credit } = clipping.details
  return [outlet, publishedOn, title, credit].some((detail) => detail !== null)
}

function slotStyle(at: number): Record<string, string> {
  const slot = slots.value[at]
  return slot === undefined ? {} : { '--a': `${slot.angle}deg`, '--lift': `${slot.lift}rem`, '--tilt': `${slot.tilt}deg` }
}

function bringToFront(index: number): void {
  turn.value = turnTowards(index, count.value, turn.value)
}

function announce(index: number): void {
  announcement.value = statusOf(index)
}

function cards(): HTMLElement[] {
  return Array.from(root.value?.querySelectorAll<HTMLElement>('.clipping') ?? [])
}

/** Index de la carte qui contient l'élément (null hors des cartes) */
function cardIndexOf(target: EventTarget | null): number | null {
  const card = target instanceof Element ? target.closest<HTMLElement>('.clipping') : null
  const index = card === null ? -1 : cards().indexOf(card)
  return index < 0 ? null : index
}

function focusCard(index: number): void {
  cards()[index]?.querySelector<HTMLElement>('.clipping__open')?.focus({ preventScroll: true })
}

/** Flèches de l'anneau : la voisine vient devant, annoncée aux lecteurs d'écran (le focus reste sur la flèche) */
function step(direction: -1 | 1): void {
  const index = wrapIndex(front.value + direction, count.value)
  bringToFront(index)
  announce(index)
}

function open(index: number): void {
  if (ring.value) bringToFront(index)
  emit('open', index)
}

/** ← et → (début et fin aussi) : depuis une carte, le focus suit la carte amenée devant ; ailleurs, l'annonce */
function onKey(event: KeyboardEvent): void {
  const target = KEYS.get(event.key)
  if (!ring.value || target === undefined || event.altKey || event.ctrlKey || event.metaKey) return
  event.preventDefault()
  const index = wrapIndex(target(front.value, count.value - 1), count.value)
  bringToFront(index)
  if (cardIndexOf(event.target) === null) announce(index)
  else focusCard(index)
}

// Glissé au doigt, au stylet ou à la souris. Écouteurs natifs : aucun écouteur Vue de plus sur le
// chemin du clic d'une carte (voir useMouseSwipe)
let press: { id: number; x: number; turn: number; width: number; card: number } | null = null
/** Un appui est en cours : le focus qu'il donne à une carte ne fait pas tourner l'anneau sous le doigt */
let pressing = false
let swallowClick = false

function onFocusIn(event: FocusEvent): void {
  const index = cardIndexOf(event.target)
  if (ring.value && !pressing && index !== null && index !== front.value) bringToFront(index)
}

function onDown(event: PointerEvent): void {
  swallowClick = false
  if (!ring.value || !event.isPrimary || (event.pointerType === 'mouse' && event.button !== 0)) return
  pressing = true
  press = { id: event.pointerId, x: event.clientX, turn: turn.value, width: cards()[0]?.offsetWidth || FALLBACK_CARD_PX, card: front.value }
}

function onMove(event: PointerEvent): void {
  if (press === null || event.pointerId !== press.id) return
  const dx = event.clientX - press.x
  if (!dragging.value) {
    if (Math.abs(dx) < DRAG_START) return
    dragging.value = true
    // Le pointeur peut sortir de l'anneau sans perdre le glissé
    stage.value?.setPointerCapture?.(press.id)
  }
  // Une largeur de carte parcourue : une carte de plus devant
  turn.value = press.turn + (dx / press.width) * orbitStep(count.value)
}

function onUp(event: PointerEvent): void {
  if (press === null || event.pointerId !== press.id) return
  const start = press
  press = null
  pressing = false
  if (!dragging.value) return
  dragging.value = false
  swallowClick = true
  const dx = event.clientX - start.x
  const card = settleCard({ start: start.card, landed: front.value, dx, swiped: Math.abs(dx) >= start.width * SWIPE_SHARE, count: count.value })
  turn.value = turnTowards(card, count.value, turn.value)
}

/** Le navigateur reprend le geste (défilement vertical de la page) : l'anneau se pose sur la carte la plus proche */
function onCancel(): void {
  press = null
  pressing = false
  if (!dragging.value) return
  dragging.value = false
  bringToFront(front.value)
}

/** Le clic qui termine un glissé n'ouvre rien ; un clic au clavier (detail 0) passe toujours */
function onClick(event: MouseEvent): void {
  if (!swallowClick || event.detail === 0) return
  swallowClick = false
  event.preventDefault()
  event.stopPropagation()
}

/**
 * Un appui relâché hors de l'anneau (sans glissé, rien ne l'y a retenu) : oublié, et l'anneau se
 * pose ; le focus au clavier le refait tourner. Relâché sur l'anneau, onUp l'a déjà traité.
 */
function onRelease(): void {
  if (press !== null) onCancel()
  pressing = false
}

let sizes: ResizeObserver | null = null
let heights: MediaQueryList | null = null

function measure(width: number): void {
  const rem = Number.parseFloat(getComputedStyle(document.documentElement).fontSize) || 16
  roomy.value = width / rem >= ROOM_REM
}

function onHeights(): void {
  tall.value = heights?.matches ?? true
}

// Une coupure en moins ou en plus : l'anneau se repose exactement sur une carte
watch(count, () => bringToFront(Math.min(front.value, Math.max(count.value - 1, 0))))

onMounted(() => {
  const element = root.value
  if (element !== null && typeof ResizeObserver !== 'undefined') {
    sizes = new ResizeObserver((entries) => {
      const box = entries[0]?.contentRect
      if (box !== undefined) measure(box.width)
    })
    sizes.observe(element)
  }
  if (typeof window.matchMedia === 'function') {
    heights = window.matchMedia(TALL_QUERY)
    onHeights()
    heights.addEventListener('change', onHeights)
  }
  const surface = stage.value
  surface?.addEventListener('pointerdown', onDown)
  surface?.addEventListener('pointermove', onMove)
  surface?.addEventListener('pointerup', onUp)
  surface?.addEventListener('pointercancel', onCancel)
  surface?.addEventListener('click', onClick, true)
  // Après ceux de l'anneau : un appui relâché ailleurs, sans glissé, est oublié
  window.addEventListener('pointerup', onRelease)
  window.addEventListener('pointercancel', onRelease)
})

onBeforeUnmount(() => {
  sizes?.disconnect()
  heights?.removeEventListener('change', onHeights)
  const surface = stage.value
  surface?.removeEventListener('pointerdown', onDown)
  surface?.removeEventListener('pointermove', onMove)
  surface?.removeEventListener('pointerup', onUp)
  surface?.removeEventListener('pointercancel', onCancel)
  surface?.removeEventListener('click', onClick, true)
  window.removeEventListener('pointerup', onRelease)
  window.removeEventListener('pointercancel', onRelease)
})

/**
 * Retour de la visionneuse : le focus va à la dernière coupure vue (la fenêtre l'aurait rendu à celle
 * qui l'a ouverte), qui vient donc devant.
 */
function returnTo(index: number): void {
  const card = wrapIndex(index, count.value)
  bringToFront(card)
  focusCard(card)
}

defineExpose({ returnTo })
</script>

<template>
  <div ref="root" class="orbit" :class="{ 'orbit--ring': ring, 'orbit--dragging': dragging }" @keydown="onKey">
    <div
      ref="stage"
      class="orbit__stage"
      :role="ring ? 'group' : undefined"
      :aria-roledescription="ring ? t('events.gallery.carouselRole') : undefined"
      :aria-label="ring ? t('events.press.ring') : undefined"
    >
      <div class="orbit__space" @focusin="onFocusIn">
        <h2 :id="titleId" class="orbit__title metal-title">{{ t('events.press.title') }}</h2>
        <ol class="orbit__ring" :style="ring ? { '--turn': `${turn}deg` } : undefined">
          <li v-for="(clipping, at) in clippings" :key="clipping.id" class="clipping" :style="ring ? slotStyle(at) : undefined">
            <figure class="clipping__paper">
              <div class="clipping__frame">
                <button
                  type="button"
                  class="clipping__open"
                  :aria-label="t('events.press.open', { index: at + 1, total: count })"
                  :aria-describedby="hasCaption(clipping) ? captionId(at) : undefined"
                  @click="open(at)"
                >
                  <!-- Dimensions déclarées : aucun décalage ; AVIF ou WebP selon le navigateur -->
                  <img
                    :src="clipping.photo.thumbUrl"
                    :srcset="`${clipping.photo.thumbUrl} 480w, ${clipping.photo.largeUrl} 1600w`"
                    :sizes="ring ? RING_SIZES : GRID_SIZES"
                    :width="clipping.photo.width"
                    :height="clipping.photo.height"
                    alt=""
                    loading="lazy"
                    decoding="async"
                    draggable="false"
                  />
                </button>
                <!-- Hors du bouton : son nom reste celui qu'on lit, la pastille se lit à côté -->
                <span v-if="clipping.photo.hidden" class="clipping__badge">{{ t('events.gallery.hiddenBadge') }}</span>
              </div>
              <figcaption v-if="hasCaption(clipping)" :id="captionId(at)" class="clipping__caption">
                <!-- Le point reste au bout du journal ; la date passe entière à la ligne, sans retrait -->
                <span v-if="clipping.details.outlet || clipping.details.publishedOn" class="clipping__source">
                  <span v-if="clipping.details.outlet" class="clipping__outlet"
                    >{{ clipping.details.outlet }}<span v-if="clipping.details.publishedOn" aria-hidden="true">&nbsp;· </span></span
                  ><time v-if="clipping.details.publishedOn" :datetime="clipping.details.publishedOn">{{ clipping.details.publishedLabel }}</time>
                </span>
                <span v-if="clipping.details.title" class="clipping__title">{{ clipping.details.title }}</span>
                <span v-if="clipping.details.credit" class="clipping__credit">{{ clipping.details.credit }}</span>
              </figcaption>
            </figure>
          </li>
        </ol>
      </div>
    </div>

    <div v-if="ring" class="orbit__controls">
      <button type="button" class="orbit__arrow" :aria-label="t('events.press.previous')" @click="step(-1)">
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>
      </button>
      <p class="orbit__status">{{ status }}</p>
      <button type="button" class="orbit__arrow" :aria-label="t('events.press.next')" @click="step(1)">
        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" /></svg>
      </button>
    </div>
    <p class="visually-hidden" aria-live="polite">{{ announcement }}</p>
    <!-- Sous l'anneau ou la grille (la mention « membres seulement ») : `ring` dit lequel est montré -->
    <slot :ring="ring" />
  </div>
</template>

<style scoped>
/* Tour de l'anneau : propriété enregistrée, que la transition anime (sans elle, l'anneau saute de carte en carte) */
@property --turn {
  syntax: '<angle>';
  inherits: true;
  initial-value: 0deg;
}

.orbit {
  container: press-orbit / inline-size;
}

/* ---------- La grille : les mêmes cartes, entières, posées à plat ---------- */
.orbit__title {
  margin: 0 0 0.75rem;
}

.orbit__ring {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 13rem), 1fr));
  align-items: start;
  gap: 1rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

/* ---------- Une coupure : le papier, l'image, la source, le titre, le crédit ---------- */
.clipping__paper {
  display: grid;
  gap: 0.6rem;
  margin: 0;
  padding: 0.6rem 0.6rem 0.8rem;
  border-radius: 3px;
  background: var(--newsprint);
  color: var(--newsprint-ink);
  box-shadow:
    0 0 0 1px var(--newsprint-edge),
    0 1.25rem 2.5rem -1.25rem var(--newsprint-shadow);
}

.clipping__frame {
  position: relative;
}

/* « Agrandir » : la loupe dit ce que fait le clic (curseur dédié, comme le grab d'un glissé) */
.clipping__open {
  display: block;
  width: 100%;
  padding: 0;
  border: 0;
  border-radius: 2px;
  background: var(--newsprint-photo);
  overflow: hidden;
  cursor: zoom-in;
}

.clipping__open img {
  display: block;
  width: 100%;
  height: auto;
}

.clipping__badge {
  position: absolute;
  inset-block-start: 0.5rem;
  inset-inline-start: 0.5rem;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  background: var(--media-scrim);
  color: var(--on-media);
  font-size: 0.8rem;
  font-weight: 700;
  pointer-events: none;
}

.clipping__caption {
  display: grid;
  gap: 0.3rem;
  font-size: 0.875rem;
  line-height: 1.35;
}

.clipping__source {
  color: var(--newsprint-accent);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

/* La date ne se coupe jamais : elle passe entière à la ligne */
.clipping__source time {
  color: var(--newsprint-muted);
  white-space: nowrap;
}

.clipping__title {
  font-family: Georgia, 'Times New Roman', Times, serif;
  font-size: 1.05rem;
  font-weight: 700;
  line-height: 1.2;
  text-wrap: balance;
  overflow-wrap: anywhere;
}

.clipping__credit {
  color: var(--newsprint-muted);
  font-size: 0.78rem;
  overflow-wrap: anywhere;
}

/* ---------- L'anneau : cartes et titre dans un même espace 3D (celles de derrière passent derrière le titre) ---------- */
/* Sur la scène, pas sur le bloc : une requête de conteneur ne règle que l'intérieur du conteneur */
.orbit--ring .orbit__stage {
  --card: clamp(10rem, 7rem + 12cqi, 14.5rem);
  --radius: clamp(8rem, 2rem + 26cqi, 21rem);
  /* Vignette bornée en hauteur (la coupure entière s'ouvre en grand) : l'anneau tient dans l'écran */
  --image-max: min(12rem, 30svh);
  /* Hauteur du centre des cartes : le titre au-dessus, la carte de devant entière en dessous */
  --ring-y: 57%;
  /* Vu d'un peu au-dessus, autour de la carte de devant : elle reste en place, celles du fond montent */
  --view: -7deg;
  /* Face au lecteur à partir de laquelle une légende se montre (les voisines aussi, sur grand écran) */
  --caption-from: 0.55;
  /* Le papier pâlit en deçà (entier dès 0,49 au-dessus : avant que la légende paraisse) */
  --paper-from: 0.1;
  position: relative;
  height: clamp(28rem, 22rem + 10cqi, 32rem);
  /* Les cartes de côté ne débordent jamais de la page */
  overflow: hidden;
  overflow: clip;
  /* La carte de devant grossit d'un quart, autour de son centre */
  perspective: calc(var(--radius) * 5);
  perspective-origin: 50% var(--ring-y);
  /* Le doigt fait toujours défiler la page de haut en bas ; de côté, il tourne l'anneau */
  touch-action: pan-y;
  cursor: grab;
  user-select: none;
  -webkit-user-select: none;
}

.orbit--dragging .orbit__stage {
  cursor: grabbing;
}

.orbit--ring .orbit__space {
  position: absolute;
  inset: 0;
  transform-style: preserve-3d;
  transform-origin: 50% var(--ring-y) var(--radius);
  transform: rotateX(var(--view));
}

/* En haut, rendu à sa place après la vue d'en haut (qui le remonte d'un rayon × sin 7°) */
.orbit--ring .orbit__title {
  position: absolute;
  inset: calc(0.25rem - var(--radius) * sin(var(--view))) 0 auto;
  margin: 0;
  font-size: clamp(1.9rem, 1rem + 5.5cqi, 4.6rem);
  line-height: 0.95;
  text-align: center;
  pointer-events: none;
}

.orbit--ring .orbit__ring {
  position: absolute;
  inset: 0;
  display: block;
  transform-style: preserve-3d;
  transform: rotateY(var(--turn));
  transition: --turn 0.8s cubic-bezier(0.22, 0.8, 0.25, 1);
}

.orbit--dragging .orbit__ring {
  transition: none;
}

.orbit--ring .clipping {
  /* 1 face au lecteur, 0 au fond de l'anneau */
  --facing: calc(0.5 + 0.5 * cos(calc(var(--a) + var(--turn))));
  position: absolute;
  inset-block-start: var(--ring-y);
  inset-inline-start: 50%;
  width: var(--card);
  /*
   * Centrée, tournée à sa place sur l'anneau, poussée au bord, retournée vers le lecteur (selon le
   * sinus de son angle : rien devant ni au fond, le plus de côté, jamais de saut d'un tour à l'autre),
   * puis soulevée et inclinée en nuage ; la carte de devant revient droite au milieu.
   */
  transform:
    translate(-50%, -50%)
    rotateY(var(--a))
    translateZ(var(--radius))
    rotateY(calc(sin(calc(var(--a) + var(--turn))) * -40deg))
    translateY(calc(var(--lift) * 0.6 * (1 - var(--facing))))
    rotate(calc(var(--tilt) * (1 - var(--facing) * 0.7)));
  /*
   * Entière sur la moitié avant ; au fond, le papier pâlit et le titre se lit par-dessus. Le papier
   * est toujours entier là où sa légende se montre (--caption-from) : aucun texte à moitié pâle.
   */
  opacity: clamp(0.3, 0.3 + (var(--facing) - var(--paper-from)) * 1.8, 1);
}

.orbit--ring .clipping__paper {
  gap: 0.45rem;
  padding: 0.5rem 0.5rem 0.65rem;
}

.orbit--ring .clipping__open img {
  max-height: var(--image-max);
  object-fit: cover;
  object-position: top;
}

/*
 * Le texte ne se montre que là où il se lit, entier ou pas du tout : un fondu lié à l'angle le
 * laissait à moitié pâle quand l'anneau s'arrêtait entre deux cartes (axe, WCAG 1.4.3, sur Plumb).
 * Une marche, puis une courte transition dans le temps.
 */
.orbit--ring .clipping__caption,
.orbit--ring .clipping__badge {
  opacity: clamp(0, (var(--facing) - var(--caption-from)) * 1000, 1);
  transition: opacity 0.2s;
}

.orbit--ring .clipping__caption {
  gap: 0.2rem;
  font-size: 0.78rem;
}

/* Journal et date sur deux lignes au plus : la date passe dessous plutôt que d'être coupée */
.orbit--ring .clipping__source {
  display: -webkit-box;
  overflow: hidden;
  font-size: 0.64rem;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
}

.orbit--ring .clipping__title {
  display: -webkit-box;
  overflow: hidden;
  font-size: 0.95rem;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 3;
  line-clamp: 3;
}

.orbit--ring .clipping__credit {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ---------- Flèches et position, sous l'anneau : aucune carte ne les couvre ---------- */
.orbit__controls {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
  margin-block-start: 0.5rem;
}

.orbit__arrow {
  display: grid;
  flex: none;
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

.orbit__arrow:hover {
  background: var(--color-surface-2);
}

.orbit__status {
  flex: 0 1 22rem;
  min-width: 0;
  margin: 0;
  color: var(--color-muted);
  font-size: 0.82rem;
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-align: center;
  text-transform: uppercase;
  overflow-wrap: anywhere;
}

/*
 * Téléphone : une scène plus basse, la vignette plus courte pour que la carte de devant tienne sous le
 * titre ; les voisines dépassent des bords, sans leur légende (coupée par le bord, elle se lirait mal)
 */
@container press-orbit (width < 40rem) {
  .orbit--ring .orbit__stage {
    --image-max: min(9.5rem, 24svh);
    --ring-y: 56%;
    --caption-from: 0.9;
    /* Voisines sans légende, à demi pâlies : la carte de devant mène (entière dès 0,84, avant sa légende) */
    --paper-from: 0.45;
    height: 26rem;
  }
}
</style>
