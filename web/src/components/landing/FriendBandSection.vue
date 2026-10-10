<script setup lang="ts">
import { LANDING } from '@/content/landing'

const band = LANDING.friendBand
</script>

<template>
  <section class="friend" aria-labelledby="friend-title" data-reveal>
    <p class="kicker">Groupe ami</p>
    <h2 id="friend-title" class="metal-title">{{ band.name }}</h2>
    <p class="friend__text">{{ band.text }}</p>
    <a :href="band.url" target="_blank" rel="noopener noreferrer external" class="friend__link">
      {{ band.linkLabel }} <span aria-hidden="true">↗</span>
      <span class="visually-hidden">(nouvel onglet)</span>
    </a>
  </section>
</template>

<style scoped>
.friend {
  display: grid;
  justify-items: start;
  gap: 0.75rem;
  padding: clamp(1.5rem, 5vw, 3rem);
  border: 1px solid var(--color-border);
  border-radius: calc(var(--radius) * 2);
  background:
    repeating-linear-gradient(135deg, transparent 0 14px, color-mix(in srgb, var(--color-border) 35%, transparent) 14px 15px),
    var(--color-surface);
}

.kicker {
  margin: 0;
  color: var(--color-link);
  font-weight: 800;
  letter-spacing: 0.2em;
  text-transform: uppercase;
}

.friend__text {
  max-width: 36rem;
  margin: 0;
  color: var(--color-muted);
}

.friend__link {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  min-height: 3rem;
  padding: 0 1.4rem;
  border: 1px solid var(--color-accent);
  border-radius: 999px;
  color: var(--color-text);
  font-weight: 800;
  text-decoration: none;
  transition: transform 0.15s ease;
}

.friend__link:hover {
  transform: translateY(-2px);
}
</style>
