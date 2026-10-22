<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

/**
 * Bandeau défilant décoratif (masqué aux lecteurs d'écran). Pause au survol et au
 * focus ; immobile quand les animations sont coupées (pied de page ou « Mon compte »).
 */
const { tm, rt } = useI18n()

const items = computed(() => (tm('landing.marquee') as unknown as Parameters<typeof rt>[0][]).map((item) => rt(item)))
</script>

<template>
  <div class="marquee" aria-hidden="true">
    <!-- Deux copies : la seconde prend le relais de la première, boucle sans saut -->
    <div class="marquee__track">
      <span v-for="copy in 2" :key="copy" class="marquee__group">
        <span v-for="(item, i) in items" :key="i" class="marquee__item">{{ item }}</span>
      </span>
    </div>
  </div>
</template>

<style scoped>
.marquee {
  overflow: hidden;
  padding-block: 0.85rem;
  border-block: 1px solid var(--color-border);
  background:
    linear-gradient(90deg, var(--color-bg), transparent 12%, transparent 88%, var(--color-bg)),
    color-mix(in srgb, var(--color-accent) 10%, var(--color-surface));
  /* Pleine largeur dans un conteneur centré */
  margin-inline: calc(-1 * var(--gutter));
}

.marquee__track {
  display: flex;
  width: max-content;
}

:root[data-motion='on'] .marquee__track {
  animation: marquee 38s linear infinite;
}

.marquee:hover .marquee__track {
  animation-play-state: paused;
}

.marquee__group {
  display: flex;
}

.marquee__item {
  display: inline-flex;
  align-items: center;
  gap: 1.5rem;
  padding-inline: 0.75rem;
  color: var(--color-text);
  font-size: clamp(1.1rem, 0.9571rem + 0.7143vw, 1.6rem);
  font-weight: 900;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  white-space: nowrap;
}

.marquee__item::after {
  content: '✦';
  color: var(--color-link);
  font-size: 0.7em;
}

.marquee__item:nth-child(even) {
  color: transparent;
  -webkit-text-stroke: 1px var(--color-muted);
}

@keyframes marquee {
  to {
    transform: translateX(-50%);
  }
}
</style>
