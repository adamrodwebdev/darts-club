<script setup lang="ts">
import { SONG } from '@/content/song'
import RiffPlayer from './RiffPlayer.vue'
</script>

<template>
  <section id="hymne" class="song" aria-labelledby="song-title">
    <div class="song__intro" data-reveal>
      <p class="kicker">Bande-son du club</p>
      <h2 id="song-title" class="metal-title">{{ SONG.title }}</h2>
      <p class="muted">{{ SONG.subtitle }} · {{ SONG.tempo }} BPM · {{ SONG.tuning }}</p>
      <p>{{ SONG.mood }}.</p>
      <RiffPlayer />
      <figure class="tab">
        <figcaption>Riff principal (tablature, corde grave en bas)</figcaption>
        <pre><code>{{ SONG.tab.join('\n') }}</code></pre>
      </figure>
    </div>

    <ol class="lyrics" data-reveal>
      <li v-for="(section, i) in SONG.sections" :key="i" :class="{ 'lyrics__part--instrumental': section.instrumental }">
        <h3>{{ section.label }}</h3>
        <p v-for="(line, j) in section.lines" :key="j">{{ line }}</p>
      </li>
    </ol>
    <p class="credits muted">{{ SONG.credits }}</p>
  </section>
</template>

<style scoped>
.song {
  display: grid;
  gap: 2rem;
}

.kicker {
  margin: 0;
  color: var(--color-link);
  font-weight: 800;
  letter-spacing: 0.15em;
  text-transform: uppercase;
}

.song__intro {
  display: grid;
  gap: 0.75rem;
  align-content: start;
}

.song__intro p {
  margin: 0;
}

.tab {
  margin: 0.5rem 0 0;
  min-width: 0;
}

.tab figcaption {
  margin-bottom: 0.4rem;
  color: var(--color-muted);
  font-size: 0.85rem;
}

/* Défilement contenu dans le bloc : jamais de débordement de page */
.tab pre {
  margin: 0;
  padding: 1rem;
  overflow-x: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-surface);
  font-family: ui-monospace, 'Cascadia Code', monospace;
  font-size: clamp(0.75rem, 2.6vw, 0.95rem);
}

.lyrics {
  display: grid;
  gap: 1.25rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.lyrics h3 {
  margin: 0 0 0.25rem;
  color: var(--color-link);
  font-size: 0.85rem;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.lyrics p {
  margin: 0;
  font-size: clamp(1rem, 2.4vw, 1.15rem);
}

.lyrics__part--instrumental p {
  color: var(--color-muted);
  font-style: italic;
}

.credits {
  grid-column: 1 / -1;
  margin: 0;
  font-size: 0.85rem;
}

@media (min-width: 1024px) {
  .song {
    grid-template-columns: 1fr 1fr;
    align-items: start;
  }

  .song__intro {
    position: sticky;
    top: 5rem;
  }
}
</style>
