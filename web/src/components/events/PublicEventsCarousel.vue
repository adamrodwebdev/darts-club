<script setup lang="ts">
import { onMounted, ref, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import FretReel from '@/components/fretboard/FretReel.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { dateBlock, formatEventDate } from '@/models/ClubEvent'
import { TIMELINE_BADGE_LABELS, timelineSlides, type TimelineSlide } from '@/models/PublicEvent'
import { LANDING } from '@/content/landing'
import { useServices } from '@/services'

/**
 * Rendez-vous ouverts à tous, du prochain au plus ancien, en carrousel 3D : la carte
 * centrale de face, les autres inclinées en retrait ; le tout se redresse à l'arrivée.
 * Rien d'affiché (et loaded = 0) s'il n'y en a aucun.
 */
const emit = defineEmits<{ loaded: [count: number] }>()
const services = useServices()
const { t } = useI18n()
const slides = ref<TimelineSlide[]>([])
const load = useAsyncAction()
const reel = useTemplateRef<{ goTo(index: number): void }>('reel')

onMounted(async () => {
  const timeline = await load.run(() => services.publicEvents.timeline())
  slides.value = timeline ? timelineSlides(timeline) : []
  emit('loaded', slides.value.length)
})

/** Carte de côté : elle vient au centre ; la carte centrale ouvre la page de l'événement */
function open(event: MouseEvent, slide: { index: number; active: boolean; navigate: (e?: MouseEvent) => unknown }): void {
  if (slide.active) {
    void slide.navigate(event)
    return
  }
  event.preventDefault()
  reel.value?.goTo(slide.index)
}

function attendance(slide: TimelineSlide): string {
  return slide.badge === 'past' ? t('publicEvents.carousel.attended', slide.event.going) : t('publicEvent.going', slide.event.going)
}
</script>

<template>
  <div v-if="slides.length" class="stage">
    <FretReel ref="reel" :items="slides" :label="t('publicEvents.carousel.label')" :item-label="(slide) => slide.event.title" variant="coverflow">
      <template #default="{ item: slide, index, active }">
        <RouterLink v-slot="{ href, navigate }" :to="`/e/${slide.event.slug}`" custom>
          <a :href="href" class="slide" :class="[`slide--${slide.badge}`, { 'slide--active': active }]" @click="open($event, { index, active, navigate })">
            <span class="slide__poster">
              <img
                v-if="slide.event.poster"
                :src="slide.event.poster.cardUrl"
                :srcset="`${slide.event.poster.cardUrl} 900w, ${slide.event.poster.largeUrl} 1600w`"
                sizes="(min-width: 30rem) 18rem, 62vw"
                :width="slide.event.poster.width"
                :height="slide.event.poster.height"
                alt=""
                loading="lazy"
                decoding="async"
              >
              <span v-else class="slide__placeholder" aria-hidden="true">
                <img :src="LANDING.logo" width="96" height="96" alt="" loading="lazy" decoding="async">
              </span>
            </span>
            <span class="slide__row">
              <span class="slide__date" aria-hidden="true">
                <b>{{ dateBlock(slide.event.startsAt).day }}</b>
                <small>{{ dateBlock(slide.event.startsAt).month }}</small>
              </span>
              <span class="slide__heading">
                <span class="tag" :class="`tag--${slide.badge}`">{{ t(TIMELINE_BADGE_LABELS[slide.badge]) }}</span>
                <span class="slide__title">{{ slide.event.title }}</span>
              </span>
            </span>
            <span class="slide__meta">
              <span>{{ formatEventDate(slide.event.startsAt) }} · {{ slide.event.city }}</span>
              <span class="muted">{{ attendance(slide) }}</span>
            </span>
          </a>
        </RouterLink>
      </template>
    </FretReel>
  </div>
</template>

<style scoped>
.stage {
  min-width: 0;
  transform-origin: 50% 100%;
}

/*
 * La scène monte en grandissant à son arrivée et redescend en repartant : de la profondeur même
 * avec une seule carte. En 2D : inclinée en 3D, elle retenait le geste du doigt commencé sur elle
 * (la page ne défilait plus).
 */
@supports (animation-timeline: view()) {
  :root[data-motion='on'] .stage {
    animation: stage-rise linear both;
    animation-timeline: view();
    animation-range: entry 10% exit 90%;
  }
}

/* Rejouée à chaque passage, dans les deux sens */
@keyframes stage-rise {
  entry 10%,
  exit 90% {
    opacity: 0.3;
    transform: translateY(2rem) scale(0.92);
  }

  entry 90%,
  exit 10% {
    opacity: 1;
    transform: none;
  }
}

.slide {
  display: grid;
  gap: 0.75rem;
  align-content: start;
  height: 100%;
  padding: 0.75rem 0.75rem 1.25rem;
  border: 1px solid var(--color-border);
  border-radius: calc(var(--radius) * 1.6);
  background: var(--color-surface);
  color: var(--color-text);
  text-decoration: none;
  transition:
    border-color 0.3s ease,
    box-shadow 0.3s ease;
}

.slide--active {
  border-color: color-mix(in srgb, var(--color-accent) 70%, var(--color-border));
  box-shadow: 0 1.5rem 3rem -1.5rem color-mix(in srgb, var(--color-accent) 75%, transparent);
}

.slide:focus-visible {
  outline: 2px solid var(--color-link);
  outline-offset: 3px;
}

/* Affiche carrée (pochette) : même hauteur pour toutes les cartes */
.slide__poster {
  display: grid;
  overflow: hidden;
  aspect-ratio: 1;
  border-radius: var(--radius);
  background: var(--color-surface-2);
}

.slide__poster > img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: top center;
}

.slide--past .slide__poster > img {
  filter: saturate(0.55);
}

.slide__placeholder {
  display: grid;
  place-items: center;
  background:
    radial-gradient(circle at 50% 40%, color-mix(in srgb, var(--color-accent) 35%, transparent), transparent 65%),
    repeating-linear-gradient(135deg, transparent 0 14px, color-mix(in srgb, var(--color-border) 45%, transparent) 14px 15px);
}

.slide__placeholder img {
  width: 40%;
  height: auto;
  border-radius: 50%;
  opacity: 0.85;
}

.slide__row {
  display: flex;
  gap: 0.75rem;
  align-items: center;
  padding-inline: 0.25rem;
}

.slide__date {
  display: grid;
  flex: none;
  place-items: center;
  width: 3.5rem;
  height: 3.5rem;
  border-radius: var(--radius);
  background: var(--color-accent);
  color: var(--color-on-accent);
  line-height: 1;
}

.slide__date b {
  font-size: 1.4rem;
}

.slide__date small {
  font-size: 0.75rem;
  text-transform: uppercase;
}

.slide--past .slide__date,
.slide--cancelled .slide__date {
  background: var(--color-surface-2);
  color: var(--color-muted);
}

.slide__heading {
  display: grid;
  gap: 0.2rem;
  min-width: 0;
}

.slide__title {
  font-size: 1.1rem;
  font-weight: 800;
  line-height: 1.25;
  overflow-wrap: anywhere;
}

.slide--cancelled .slide__title {
  text-decoration: line-through;
}

.slide__meta {
  display: grid;
  gap: 0.15rem;
  padding-inline: 0.25rem;
  font-size: 0.92rem;
}

.tag {
  justify-self: start;
  padding: 0.1rem 0.55rem;
  border-radius: 999px;
  background: var(--color-surface-2);
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.04em;
}

.tag--next,
.tag--live {
  background: color-mix(in srgb, var(--color-accent) 18%, var(--color-surface));
  color: var(--color-link);
}

.tag--cancelled {
  color: var(--color-danger);
}
</style>
