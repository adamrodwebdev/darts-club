<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { SONG, type InstrumentalKey } from '@/content/song'
import SplitText from '@/components/motion/SplitText.vue'
import RiffPlayer from './RiffPlayer.vue'

const { t, tm, rt, locale } = useI18n()

/** Indications de jeu d'une partie instrumentale, dans la langue courante */
function directions(key: InstrumentalKey): string[] {
  return (tm(`song.directions.${key}`) as unknown as Parameters<typeof rt>[0][]).map((line) => rt(line))
}
</script>

<template>
  <section id="hymne" class="song" aria-labelledby="song-title">
    <div class="song__intro" data-reveal>
      <p class="kicker">{{ t('song.kicker') }}</p>
      <SplitText id="song-title" tag="h2" class="metal-title" :text="SONG.title" />
      <p class="muted">{{ t('song.subtitle') }} · {{ SONG.tempo }} BPM · {{ SONG.tuning }}</p>
      <p>{{ t('song.mood') }}</p>
      <RiffPlayer />
      <figure class="tab">
        <figcaption>{{ t('song.tabCaption') }}</figcaption>
        <pre><code>{{ SONG.tab.join('\n') }}</code></pre>
      </figure>
    </div>

    <div class="lyrics-block" data-reveal>
      <p v-if="locale !== 'fr'" class="muted lyrics-note">{{ t('song.lyricsLanguage') }}</p>
      <ol class="lyrics">
        <li v-for="(section, i) in SONG.sections" :key="i" :class="{ 'lyrics__part--instrumental': section.instrumental }">
          <h3>{{ t(`song.sections.${section.key}`) }}</h3>
          <template v-if="section.instrumental">
            <p v-for="(line, j) in directions(section.key)" :key="j">{{ line }}</p>
          </template>
          <!-- Paroles chantées en français quelle que soit la langue de l'interface -->
          <p v-for="(line, j) in section.lines" v-else :key="j" lang="fr">{{ line }}</p>
        </li>
      </ol>
    </div>
    <p class="credits muted">{{ t('song.credits') }}</p>
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

.lyrics-note {
  margin: 0 0 1rem;
  font-size: 0.9rem;
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
