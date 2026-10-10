<script setup lang="ts">
import { LANDING } from '@/content/landing'
</script>

<template>
  <section aria-labelledby="influences-title" class="influences">
    <div data-reveal>
      <p class="kicker">Dans les enceintes du club</p>
      <h2 id="influences-title" class="metal-title">Nos influences</h2>
      <p class="muted">
        Les groupes qui tournent pendant nos soirées. Va les écouter, achète leurs disques, va les voir en concert :
        les liens mènent vers leurs pages officielles.
      </p>
    </div>
    <ul class="bands" data-reveal>
      <li v-for="band in LANDING.influences" :key="band.name">
        <a :href="band.url" target="_blank" rel="noopener noreferrer external" class="band">
          <span class="band__name">{{ band.name }}</span>
          <span class="band__genre">{{ band.genre }}</span>
          <span class="visually-hidden">(nouvel onglet)</span>
        </a>
      </li>
    </ul>
    <p class="legal muted">
      Noms cités à titre de référence musicale. Le club n’est ni affilié ni soutenu par ces artistes ; leurs noms et
      marques appartiennent à leurs titulaires.
    </p>
  </section>
</template>

<style scoped>
.influences {
  display: grid;
  gap: 1.5rem;
}

.kicker {
  margin: 0;
  color: var(--color-link);
  font-weight: 800;
  letter-spacing: 0.15em;
  text-transform: uppercase;
}

.bands {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(15rem, 100%), 1fr));
  gap: 0.75rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.band {
  display: grid;
  gap: 0.2rem;
  min-height: 44px;
  padding: 1rem 1.1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: linear-gradient(135deg, var(--color-surface), var(--color-surface-2));
  color: var(--color-text);
  text-decoration: none;
  transition:
    transform 0.2s ease,
    border-color 0.2s ease;
}

.band:hover,
.band:focus-visible {
  transform: translateY(-3px) rotate(-0.4deg);
  border-color: var(--color-accent);
}

.band__name {
  font-size: 1.15rem;
  font-weight: 900;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.band__genre {
  color: var(--color-muted);
  font-size: 0.85rem;
}

.legal {
  margin: 0;
  font-size: 0.8rem;
}
</style>
