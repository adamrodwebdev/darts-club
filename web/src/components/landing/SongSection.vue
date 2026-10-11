<script setup lang="ts">
import { computed, useTemplateRef } from 'vue'
import { useI18n } from 'vue-i18n'
import { SONG, type InstrumentalKey } from '@/content/song'
import SplitText from '@/components/motion/SplitText.vue'
import { useMediaPlayback } from '@/composables/useMediaPlayback'
import { formatClock } from '@/i18n/format'
import AnthemPlayer from './AnthemPlayer.vue'

const { t, tm, rt, locale } = useI18n()
const audio = useTemplateRef<HTMLAudioElement>('audio')
const playback = useMediaPlayback(audio)
const { playing, time, started, failed } = playback

/** Indications de jeu d'une partie instrumentale, dans la langue courante */
function directions(key: InstrumentalKey): string[] {
  return (tm(`song.directions.${key}`) as unknown as Parameters<typeof rt>[0][]).map((line) => rt(line))
}

/** Partie en cours d'écoute (mise en évidence dans les paroles) */
const current = computed(() => {
  if (!started.value) return -1
  return SONG.sections.reduce((found, section, i) => (section.start <= time.value ? i : found), -1)
})

async function toggle(): Promise<void> {
  announce()
  await playback.toggle()
}

async function playSection(start: number): Promise<void> {
  announce()
  await playback.playFrom(start)
}

/** Titre et visuel affichés par le système (écran verrouillé, commandes multimédia) */
function announce(): void {
  if (!('mediaSession' in navigator) || typeof MediaMetadata === 'undefined') return
  navigator.mediaSession.metadata = new MediaMetadata({
    title: SONG.title,
    artist: 'Skull Darts 71',
    artwork: [{ src: '/icon-512.png', sizes: '512x512', type: 'image/png' }],
  })
}
</script>

<template>
  <section id="hymne" class="song" aria-labelledby="song-title">
    <div class="song__intro" data-reveal>
      <p class="kicker">{{ t('song.kicker') }}</p>
      <SplitText id="song-title" tag="h2" class="metal-title levitate" :text="SONG.title" />
      <p class="muted">{{ t('song.subtitle') }} · {{ formatClock(SONG.duration) }}</p>
      <p>{{ t('song.mood') }}</p>
      <!-- Rien n'est chargé avant la première lecture : aucun poids ajouté à la page -->
      <audio ref="audio" preload="none">
        <source v-for="recording in SONG.recordings" :key="recording.src" :src="recording.src" :type="recording.type">
      </audio>
      <AnthemPlayer
        :playing="playing"
        :time="time"
        :duration="SONG.duration"
        :failed="failed"
        @toggle="toggle"
        @seek="playback.seek"
      />
    </div>

    <div class="lyrics-block" data-reveal>
      <p v-if="locale !== 'fr'" class="muted lyrics-note">{{ t('song.lyricsLanguage') }}</p>
      <p class="muted lyrics-note">{{ t('song.chaptersHint') }}</p>
      <ol class="lyrics">
        <li
          v-for="(section, i) in SONG.sections"
          :key="i"
          :class="{ 'lyrics__part--instrumental': section.instrumental, 'lyrics__part--current': i === current }"
          :aria-current="i === current ? 'time' : undefined"
        >
          <div class="lyrics__head">
            <h3>{{ t(`song.sections.${section.key}`) }}</h3>
            <button
              type="button"
              class="chapter"
              :aria-label="t('song.playFrom', { section: t(`song.sections.${section.key}`), time: formatClock(section.start) })"
              @click="playSection(section.start)"
            >
              <svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true"><path d="M7 4l13 8-13 8z" fill="currentColor" /></svg>
              <time :datetime="`PT${section.start}S`">{{ formatClock(section.start) }}</time>
            </button>
          </div>
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

.lyrics li {
  padding-left: 0.75rem;
  border-left: 3px solid transparent;
  transition: border-color 0.3s ease;
}

.lyrics .lyrics__part--current {
  border-left-color: var(--color-accent);
}

.lyrics__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.25rem 0.75rem;
  margin-bottom: 0.25rem;
}

/* Minutage cliquable : 44 px de haut pour le doigt, discret à l'œil */
.chapter {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  min-height: 2.75rem;
  padding: 0 0.6rem;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  background: transparent;
  color: var(--color-muted);
  font: inherit;
  font-size: 0.85rem;
  font-variant-numeric: tabular-nums;
  cursor: pointer;
}

.chapter:hover,
.lyrics__part--current .chapter {
  border-color: var(--color-accent);
  color: var(--color-text);
}

.lyrics h3 {
  margin: 0;
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
