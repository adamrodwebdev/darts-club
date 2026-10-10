<script setup lang="ts">
import { useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import HeroDartboard from '@/components/landing/HeroDartboard.vue'
import FriendBandSection from '@/components/landing/FriendBandSection.vue'
import SongSection from '@/components/landing/SongSection.vue'
import { useReveal } from '@/composables/useReveal'
import { LANDING } from '@/content/landing'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const { t } = useI18n()
const root = useTemplateRef<HTMLElement>('root')
useReveal(root)

const ICONS: Record<string, string> = {
  target: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10zM12 11a1 1 0 1 0 0 2 1 1 0 0 0 0-2z',
  night: 'M20 14A8 8 0 1 1 10 4a6 6 0 0 0 10 10z',
  crew: 'M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM16 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM2 20c0-3 3-5 6-5s6 2 6 5M14 15c3 0 8 1 8 5',
}
</script>

<template>
  <div ref="root" class="landing">
    <section class="hero">
      <div class="hero__text">
        <p class="kicker">{{ t('landing.kicker') }}</p>
        <h1 class="metal-title hero__title">{{ LANDING.name }}</h1>
        <p class="hero__tagline">{{ t('landing.tagline') }}</p>
        <p class="hero__intro">{{ t('landing.intro') }}</p>
        <div class="hero__cta">
          <template v-if="auth.isAuthenticated">
            <RouterLink to="/evenements" class="btn btn--primary">{{ t('landing.upcoming') }}</RouterLink>
            <RouterLink to="/infos" class="btn btn--ghost">{{ t('landing.infos') }}</RouterLink>
          </template>
          <template v-else>
            <RouterLink to="/inscription" class="btn btn--primary">{{ t('landing.join') }}</RouterLink>
            <RouterLink to="/connexion" class="btn btn--ghost">{{ t('landing.login') }}</RouterLink>
          </template>
          <a href="#hymne" class="btn btn--link">{{ t('landing.anthem') }}</a>
        </div>
      </div>
      <HeroDartboard class="hero__board" />
    </section>

    <section class="pillars" :aria-label="t('landing.pillarsLabel')">
      <article v-for="pillar in LANDING.pillars" :key="pillar.key" class="pillar" data-reveal>
        <svg viewBox="0 0 24 24" width="32" height="32" aria-hidden="true">
          <path :d="ICONS[pillar.icon]" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <h2>{{ t(`landing.pillars.${pillar.key}.title`) }}</h2>
        <p>{{ t(`landing.pillars.${pillar.key}.text`) }}</p>
      </article>
    </section>

    <SongSection />

    <FriendBandSection />

    <section class="join" data-reveal aria-labelledby="join-title">
      <h2 id="join-title" class="metal-title">{{ t('landing.cta.title') }}</h2>
      <p>{{ t('landing.cta.text') }}</p>
      <i18n-t keypath="landing.cta.facebook" tag="p" scope="global">
        <template #link>
          <a :href="LANDING.facebook" target="_blank" rel="noopener noreferrer external">{{ t('landing.cta.facebookLink') }}<span class="visually-hidden"> {{ t('common.newTab') }}</span></a>
        </template>
      </i18n-t>
      <RouterLink :to="auth.isAuthenticated ? '/evenements' : '/inscription'" class="btn btn--primary">
        {{ auth.isAuthenticated ? t('landing.cta.calendar') : t('landing.cta.signUp') }}
      </RouterLink>
    </section>
  </div>
</template>

<style scoped>
.landing {
  /* La fléchette arrive de hors écran : on la rogne sans créer de défilement */
  overflow-x: clip;
  display: grid;
  gap: clamp(3.5rem, 9vw, 7rem);
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
  min-height: min(80vh, 46rem);
}

.hero__text {
  display: grid;
  gap: 1rem;
}

.hero__title {
  margin: 0;
  /* « Skull Darts 71 » tient sur 2 lignes de 320 px à grand écran */
  font-size: clamp(2.6rem, 11vw, 6.5rem);
  line-height: 0.92;
  overflow-wrap: anywhere;
}

.hero__tagline {
  margin: 0;
  font-size: clamp(1.25rem, 4vw, 1.9rem);
  font-weight: 800;
}

.hero__intro {
  max-width: 34rem;
  margin: 0;
  color: var(--color-muted);
  font-size: clamp(1rem, 2.5vw, 1.15rem);
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

.pillars {
  display: grid;
  gap: 1rem;
}

.pillar {
  padding: 1.5rem;
  border: 1px solid var(--color-border);
  border-radius: calc(var(--radius) * 1.5);
  background:
    radial-gradient(circle at 100% 0%, color-mix(in srgb, var(--color-accent) 18%, transparent), transparent 60%),
    var(--color-surface);
}

.pillar svg {
  color: var(--color-link);
}

.pillar h2 {
  margin: 0.75rem 0 0.4rem;
  font-size: 1.35rem;
  text-transform: uppercase;
}

.pillar p {
  margin: 0;
  color: var(--color-muted);
}

.join {
  display: grid;
  justify-items: center;
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
  font-size: clamp(1.8rem, 6vw, 3rem);
}

.join p {
  margin: 0;
  color: var(--color-muted);
}

@media (min-width: 768px) {
  .pillars {
    grid-template-columns: repeat(3, 1fr);
  }
}

@media (min-width: 1024px) {
  .hero {
    grid-template-columns: 1.1fr 1fr;
  }
}
</style>
