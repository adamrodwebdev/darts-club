<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import PublicEventsCarousel from '@/components/events/PublicEventsCarousel.vue'
import FretReel from '@/components/fretboard/FretReel.vue'
import GuitarString from '@/components/fretboard/GuitarString.vue'
import FriendBandSection from '@/components/landing/FriendBandSection.vue'
import HeroDartboard from '@/components/landing/HeroDartboard.vue'
import SongSection from '@/components/landing/SongSection.vue'
import MarqueeBand from '@/components/motion/MarqueeBand.vue'
import SplitText from '@/components/motion/SplitText.vue'
import { useStages } from '@/composables/useStages'
import { LANDING } from '@/content/landing'
import { useAuthStore } from '@/stores/auth'

/**
 * Accueil « manche de guitare » : on gratte de haut en bas d'une corde (section) à
 * l'autre, et on glisse de gauche à droite sur chacune (cartes du club, rendez-vous,
 * parties de l'hymne) au lieu d'allonger la page.
 */
const auth = useAuthStore()
const { t } = useI18n()
/**
 * Cordes rendues une par tâche après le héros : le démarrage n'a aucune tâche longue.
 * Lien vers une ancre (« #hymne ») : tout de suite, la cible doit exister.
 */
const STRINGS = 4
const strings = useStages(STRINGS, { all: useRoute().hash !== '' })
/** Corde des rendez-vous affichée seulement s'il en existe d'ouverts à tous */
const publicEvents = ref(0)

const ICONS: Record<string, string> = {
  target: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10zM12 11a1 1 0 1 0 0 2 1 1 0 0 0 0-2z',
  night: 'M20 14A8 8 0 1 1 10 4a6 6 0 0 0 10 10z',
  crew: 'M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM16 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM2 20c0-3 3-5 6-5s6 2 6 5M14 15c3 0 8 1 8 5',
}

function stringLabel(n: number, name: string): string {
  return t('fretboard.string', { n, name: t(`fretboard.names.${name}`) })
}
</script>

<template>
  <div class="landing fretboard">
    <section class="hero">
      <div class="hero__text">
        <p class="kicker">{{ t('landing.kicker') }}</p>
        <SplitText tag="h1" class="metal-title hero__title levitate" :text="LANDING.name" rise />
        <SplitText tag="p" class="hero__tagline" :text="t('landing.tagline')" rise />
        <p class="hero__intro">{{ t('landing.intro') }}</p>
        <div class="hero__cta">
          <template v-if="auth.isAuthenticated">
            <RouterLink to="/evenements" class="btn btn--primary sheen-button">{{ t('landing.upcoming') }}</RouterLink>
            <RouterLink to="/infos" class="btn btn--ghost">{{ t('landing.infos') }}</RouterLink>
          </template>
          <template v-else>
            <RouterLink to="/inscription" class="btn btn--primary sheen-button">{{ t('landing.join') }}</RouterLink>
            <RouterLink to="/connexion" class="btn btn--ghost">{{ t('landing.login') }}</RouterLink>
          </template>
          <a href="#hymne" class="btn btn--link">{{ t('landing.anthem') }}</a>
        </div>
      </div>
      <HeroDartboard class="hero__board" />
      <p class="hint">
        <svg viewBox="0 0 28 22" width="28" height="22" aria-hidden="true">
          <path d="M2 3h24M2 7h24M2 11h24M2 15h24M2 19h24" fill="none" stroke="currentColor" stroke-width="1.5" />
          <path class="hint__pick" d="M14 1v20" fill="none" stroke-width="2.5" stroke-linecap="round" />
        </svg>
        {{ t('fretboard.hint') }}
      </p>
    </section>

    <MarqueeBand />

    <GuitarString v-if="strings >= 1" :strings="[2]" :label="stringLabel(2, 'club')" aria-labelledby="club-title">
      <SplitText id="club-title" tag="h2" class="metal-title levitate" :text="t('landing.clubTitle')" />
      <FretReel
        :items="LANDING.pillars"
        :label="t('landing.pillarsLabel')"
        :item-label="(pillar) => t(`landing.pillars.${pillar.key}.title`)"
        variant="grid-wide"
      >
        <template #default="{ item: pillar }">
          <article class="pillar">
            <svg viewBox="0 0 24 24" width="32" height="32" aria-hidden="true">
              <path :d="ICONS[pillar.icon]" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <h3>{{ t(`landing.pillars.${pillar.key}.title`) }}</h3>
            <p>{{ t(`landing.pillars.${pillar.key}.text`) }}</p>
          </article>
        </template>
      </FretReel>
    </GuitarString>

    <!-- Chargée après l'affichage, sous la ligne de flottaison : pas de décalage visible -->
    <GuitarString
      v-if="strings >= 2"
      v-show="publicEvents > 0"
      :strings="[3]"
      :label="stringLabel(3, 'events')"
      aria-labelledby="public-events-title"
    >
      <h2 id="public-events-title" class="metal-title">{{ t('publicEvents.carousel.title') }}</h2>
      <p class="muted string-intro">{{ t('publicEvents.carousel.intro') }}</p>
      <PublicEventsCarousel @loaded="publicEvents = $event" />
      <RouterLink to="/evenements" class="btn btn--link more">{{ t('publicEvents.all') }}</RouterLink>
    </GuitarString>

    <GuitarString v-if="strings >= 3" id="hymne" :strings="[4]" :label="stringLabel(4, 'anthem')" aria-labelledby="song-title">
      <SongSection />
    </GuitarString>

    <GuitarString
      v-if="strings >= 4"
      id="rejoindre"
      :strings="[5, 6]"
      :label="t('fretboard.strings', { first: 5, second: 6, name: t('fretboard.names.join') })"
      :aria-label="t('fretboard.names.join')"
    >
      <div class="duo">
        <FriendBandSection />
        <section class="join" aria-labelledby="join-title">
          <SplitText id="join-title" tag="h2" class="metal-title levitate" :text="t('landing.cta.title')" />
          <p>{{ t('landing.cta.text') }}</p>
          <i18n-t keypath="landing.cta.facebook" tag="p" scope="global">
            <template #link>
              <a :href="LANDING.facebook" target="_blank" rel="noopener noreferrer external">{{ t('landing.cta.facebookLink') }}<span class="visually-hidden"> {{ t('common.newTab') }}</span></a>
            </template>
          </i18n-t>
          <RouterLink :to="auth.isAuthenticated ? '/evenements' : '/inscription'" class="btn btn--primary sheen-button">
            {{ auth.isAuthenticated ? t('landing.cta.calendar') : t('landing.cta.signUp') }}
          </RouterLink>
        </section>
      </div>
    </GuitarString>
    <!-- Place réservée tant que des cordes manquent : le pied de page reste hors de vue, rien ne saute -->
    <div v-if="strings < STRINGS" class="strings-pending" aria-hidden="true" />
  </div>
</template>

<style scoped>
.landing {
  /* La fléchette arrive de hors écran : on la rogne sans créer de défilement */
  overflow-x: clip;
  display: grid;
  gap: clamp(2.5rem, 6vw, 4.5rem);
  max-width: var(--max-width);
  margin: 0 auto;
  padding: clamp(1.5rem, 5vw, 3rem) var(--gutter) 4rem;
}

.kicker {
  margin: 0;
  color: var(--color-link);
  font-weight: 800;
  letter-spacing: 0.2em;
  text-transform: uppercase;
}

.hero {
  display: grid;
  gap: 2rem;
  align-items: center;
  min-height: min(80svh, 46rem);
  scroll-snap-align: start;
}

/* Mode d'emploi du manche : gratter de haut en bas, glisser de côté */
.hint {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin: 0;
  padding: 0.75rem 1rem;
  border: 1px dashed var(--color-border);
  border-radius: calc(var(--radius) * 1.3);
  color: var(--color-muted);
  font-size: 0.9rem;
}

.hint svg {
  flex: none;
}

.hint__pick {
  stroke: var(--color-accent);
}

.string-intro {
  margin: 0;
}

.strings-pending {
  min-height: 100svh;
}

.more {
  justify-self: start;
}

.hero__text {
  display: grid;
  gap: 1rem;
}

.hero__title {
  margin: 0;
  /* « Skull Darts 71 » tient sur 2 lignes de 320 px à grand écran */
  font-size: clamp(2.6rem, 1.4857rem + 5.5714vw, 6.5rem);
  line-height: 0.92;
  overflow-wrap: anywhere;
}

.hero__tagline {
  margin: 0;
  font-size: clamp(1.25rem, 1.0643rem + 0.9286vw, 1.9rem);
  font-weight: 800;
}

.hero__intro {
  max-width: 34rem;
  margin: 0;
  color: var(--color-muted);
  font-size: clamp(1rem, 0.9571rem + 0.2143vw, 1.15rem);
}

.hero__cta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-top: 0.5rem;
}

.btn {
  display: inline-flex;
  align-items: center;
  min-height: 3rem;
  padding: 0 1.4rem;
  border: 1px solid transparent;
  border-radius: 999px;
  font-weight: 800;
  text-decoration: none;
  transition: transform 0.15s ease;
}

.btn:hover {
  transform: translateY(-2px);
}

.btn--primary {
  background: var(--color-accent);
  color: var(--color-on-accent);
}

.btn--ghost {
  border-color: var(--color-border);
  color: var(--color-text);
}

.btn--link {
  color: var(--color-link);
}

.pillar {
  height: 100%;
  padding: 1.5rem;
  border: 1px solid var(--color-border);
  border-radius: calc(var(--radius) * 1.5);
  background:
    radial-gradient(circle at 100% 0%, color-mix(in srgb, var(--color-accent) 18%, transparent), transparent 60%),
    var(--color-surface);
}

.pillar {
  transition:
    transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1),
    box-shadow 0.35s ease,
    border-color 0.35s ease;
}

:root[data-motion='on'] .pillar:hover {
  transform: translateY(-6px);
  border-color: color-mix(in srgb, var(--color-accent) 60%, var(--color-border));
  box-shadow: 0 1.25rem 2.5rem -1.5rem color-mix(in srgb, var(--color-accent) 70%, transparent);
}

.pillar svg {
  color: var(--color-link);
}

.pillar h3 {
  margin: 0.75rem 0 0.4rem;
  font-size: 1.35rem;
  text-transform: uppercase;
}

.pillar p {
  margin: 0;
  color: var(--color-muted);
}

/* Cordes 5 et 6 : groupe ami et adhésion côte à côte dès que la place le permet */
.duo {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 22rem), 1fr));
  gap: 1rem;
}

.join {
  display: grid;
  justify-items: center;
  align-content: center;
  gap: 1rem;
  padding: clamp(2rem, 6vw, 4rem) 1.5rem;
  border-radius: calc(var(--radius) * 2);
  background:
    radial-gradient(circle at 50% 120%, color-mix(in srgb, var(--color-accent) 45%, transparent), transparent 65%),
    var(--color-surface);
  text-align: center;
}

.join h2 {
  margin: 0;
  font-size: clamp(1.8rem, 1.4571rem + 1.7143vw, 3rem);
}

.join p {
  margin: 0;
  color: var(--color-muted);
}

@media (width >= 64rem) {
  .hero {
    grid-template-columns: 1.1fr 1fr;
  }

  .hint {
    grid-column: 1 / -1;
  }
}
</style>
