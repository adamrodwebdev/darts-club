<script setup lang="ts">
import { computed, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { SONG, type InstrumentalKey } from '@/content/song'
import FretReel from '@/components/fretboard/FretReel.vue'
import SplitText from '@/components/motion/SplitText.vue'
import { useMediaPlayback } from '@/composables/useMediaPlayback'
import { useStages } from '@/composables/useStages'
import { formatClock } from '@/i18n/format'
import { vReplay } from '@/motion/vReplay'
import AnthemPlayer from './AnthemPlayer.vue'

const { t, tm, rt, locale } = useI18n()
const audio = useTemplateRef<HTMLAudioElement>('audio')
const deck = useTemplateRef<{ goTo(index: number): void }>('deck')
const playback = useMediaPlayback(audio)
const { playing, time, started, failed } = playback

/**
 * Cartes des paroles rendues par paquets, chacun dans sa propre image, après l'intro : leur mise
 * en page (surtout du texte) ne forme pas une tâche longue au chargement. Le premier paquet
 * contient la carte la plus haute : une fois là, la bande ne change plus de hauteur.
 */
const CARDS_PER_TASK = 3
const batches = useStages(Math.ceil(SONG.sections.length / CARDS_PER_TASK), { all: false })
const sections = computed(() => SONG.sections.slice(0, batches.value * CARDS_PER_TASK))

/** Indications de jeu d'une partie instrumentale, dans la langue courante */
function directions(key: InstrumentalKey): string[] {
  return (tm(`song.directions.${key}`) as unknown as Parameters<typeof rt>[0][]).map((line) => rt(line))
}

/** Partie en cours d'écoute (mise en évidence dans les paroles) */
const current = computed(() => {
  if (!started.value) return -1
  return SONG.sections.reduce((found, section, i) => (section.start <= time.value ? i : found), -1)
})

// Pendant l'écoute, les paroles suivent : la carte de la partie en cours vient se caler
watch(current, (index) => {
  if (index >= 0 && playing.value) deck.value?.goTo(index)
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
  <div class="song">
    <div v-replay class="song__intro">
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

    <div class="song__deck">
      <p v-if="locale !== 'fr'" class="muted lyrics-note">{{ t('song.lyricsLanguage') }}</p>
      <p class="muted lyrics-note">{{ t('song.chaptersHint') }}</p>
      <FretReel
        ref="deck"
        class="lyrics"
        :items="sections"
        :label="t('song.partsLabel')"
        :item-label="(section) => t(`song.sections.${section.key}`)"
        :current="current >= 0 ? current : null"
        current-type="time"
        variant="deck"
      >
        <template #default="{ item: section, index }">
          <article class="part" :class="{ 'part--instrumental': section.instrumental, 'part--current': index === current }">
            <div class="part__head">
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
          </article>
        </template>
      </FretReel>
    </div>
    <p class="credits muted">{{ t('song.credits') }}</p>
  </div>
</template>

<style scoped>
.song {
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

.song__intro {
  display: grid;
  gap: 0.75rem;
  align-content: start;
}

.song__intro p {
  margin: 0;
}

.song__deck {
  display: grid;
  min-width: 0;
}

.lyrics-note {
  margin: 0 0 0.25rem;
  font-size: 0.9rem;
}

/* Une partie de l'hymne : une carte de la corde, mise en avant pendant son écoute */
.part {
  display: grid;
  gap: 0.4rem;
  align-content: start;
  padding: 1.1rem 1.25rem 1.25rem;
  border: 1px solid var(--color-border);
  border-radius: calc(var(--radius) * 1.6);
  background: var(--color-surface);
  transition:
    border-color 0.3s ease,
    box-shadow 0.3s ease;
}

.part--current {
  border-color: var(--color-accent);
  box-shadow: 0 0 0 1px var(--color-accent);
}

.part__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.25rem 0.75rem;
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
.part--current .chapter {
  border-color: var(--color-accent);
  color: var(--color-text);
}

.part h3 {
  margin: 0;
  color: var(--color-link);
  font-size: 0.85rem;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.part p {
  margin: 0;
  font-size: clamp(1rem, 0.9571rem + 0.2143vw, 1.15rem);
}

.part--instrumental p {
  color: var(--color-muted);
  font-style: italic;
}

.credits {
  grid-column: 1 / -1;
  margin: 0;
  font-size: 0.85rem;
}

@media (width >= 64rem) {
  .song {
    grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
    align-items: start;
  }
}
</style>
