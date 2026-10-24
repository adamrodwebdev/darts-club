<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, useRoute } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import QrPoster from '@/components/events/QrPoster.vue'
import type { QrPosterContent } from '@/components/events/QrPosterContent'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { CLUB_TIME_ZONE, formatDate } from '@/i18n/format'
import type { EventSummary } from '@/models/ClubEvent'
import { printOnly } from '@/print/printOnly'
import { useServices } from '@/services'

const services = useServices()
const route = useRoute()
const { t } = useI18n()

const SITE = 'site'
/** Soirées publiques à venir : elles seules ont une page à scanner */
const evenings = ref<EventSummary[]>([])
const target = ref(typeof route.query.soiree === 'string' ? route.query.soiree : SITE)
const ink = ref<'light' | 'dark'>('light')
const size = ref<'a5' | 'a4'>('a5')
const load = useAsyncAction()
const host = window.location.host

onMounted(async () => {
  const data = await load.run(() => services.events.list({ period: 'upcoming' }))
  if (!data) return
  evenings.value = data.items.filter((e) => e.isPublic && e.publicPath !== null && !e.cancelled)
  // Lien vers une soirée sans page publique : affiche du site
  if (!evenings.value.some((e) => e.id === target.value)) target.value = SITE
})

const evening = computed(() => evenings.value.find((e) => e.id === target.value) ?? null)

function capitalized(text: string): string {
  return text.charAt(0).toLocaleUpperCase() + text.slice(1)
}

/** « 20 h » (« 8 PM ») pour une heure ronde, « 20:30 » sinon */
function timeLabel(date: Date): string {
  const options: Intl.DateTimeFormatOptions = { hour: 'numeric', timeZone: CLUB_TIME_ZONE }
  const minutes = Number(formatDate(date, { minute: 'numeric', timeZone: CLUB_TIME_ZONE }))
  return formatDate(date, minutes === 0 ? options : { ...options, minute: '2-digit' })
}

const content = computed<QrPosterContent>(() => {
  const e = evening.value
  if (!e) {
    return {
      eyebrow: null,
      heading: t('events.qrPoster.siteHeading'),
      subheading: t('events.qrPoster.siteSubheading'),
      primary: host,
      secondary: t('events.qrPoster.siteCta'),
      footer: t('events.qrPoster.footer'),
      qrSrc: services.events.qrUrl(null),
      qrAlt: t('events.qrPoster.qrAlt', { address: host }),
    }
  }
  const address = `${host}${e.publicPath ?? ''}`
  return {
    eyebrow: e.title,
    heading: capitalized(formatDate(e.startsAt, { weekday: 'long', day: 'numeric', month: 'long', timeZone: CLUB_TIME_ZONE })),
    subheading: [timeLabel(e.startsAt), e.city].filter(Boolean).join(' · '),
    primary: t('events.qrPoster.eventCta'),
    secondary: address,
    footer: t('events.qrPoster.footer'),
    qrSrc: services.events.qrUrl(e.id),
    qrAlt: t('events.qrPoster.qrAlt', { address }),
  }
})

/** Seule l'affiche part à l'impression, au format choisi (styles d'impression de QrPoster) */
function print(): void {
  printOnly('qr-poster')
}
</script>

<template>
  <section class="page stack">
    <RouterLink to="/evenements" class="back">{{ t('events.detail.back') }}</RouterLink>
    <h1>{{ t('events.qrPoster.title') }}</h1>
    <p class="muted intro">{{ t('events.qrPoster.intro') }}</p>

    <div class="layout">
      <form class="card stack controls" @submit.prevent="print">
        <fieldset class="choices">
          <legend>{{ t('events.qrPoster.target') }}</legend>
          <label class="choice">
            <input v-model="target" type="radio" name="target" :value="SITE" />
            <span>{{ t('events.qrPoster.targetSite') }} <span class="muted">· {{ host }}</span></span>
          </label>
          <label v-for="e in evenings" :key="e.id" class="choice">
            <input v-model="target" type="radio" name="target" :value="e.id" />
            <span>{{ e.title }} <span class="muted">· {{ formatDate(e.startsAt, { dateStyle: 'medium', timeZone: CLUB_TIME_ZONE }) }}</span></span>
          </label>
          <p v-if="!load.pending.value && !evenings.length" class="muted hint">{{ t('events.qrPoster.noPublicEvening') }}</p>
        </fieldset>

        <fieldset class="choices">
          <legend>{{ t('events.qrPoster.ink') }}</legend>
          <label class="choice">
            <input v-model="ink" type="radio" name="ink" value="light" />
            <span>{{ t('events.qrPoster.inkLight') }} <span class="muted">· {{ t('events.qrPoster.inkLightHint') }}</span></span>
          </label>
          <label class="choice">
            <input v-model="ink" type="radio" name="ink" value="dark" />
            <span>{{ t('events.qrPoster.inkDark') }} <span class="muted">· {{ t('events.qrPoster.inkDarkHint') }}</span></span>
          </label>
        </fieldset>

        <fieldset class="choices choices--inline">
          <legend>{{ t('events.qrPoster.size') }}</legend>
          <label class="choice">
            <input v-model="size" type="radio" name="size" value="a5" />
            <span>{{ t('events.qrPoster.sizeA5') }} <span class="muted">· {{ t('events.qrPoster.sizeA5Hint') }}</span></span>
          </label>
          <label class="choice">
            <input v-model="size" type="radio" name="size" value="a4" />
            <span>{{ t('events.qrPoster.sizeA4') }}</span>
          </label>
        </fieldset>

        <div class="buttons">
          <BaseButton type="submit">{{ t('events.qrPoster.print') }}</BaseButton>
          <a class="link-button" :href="content.qrSrc" download>{{ t('events.qrPoster.download') }}</a>
        </div>
        <p class="muted hint">{{ t('events.qrPoster.printHint') }}</p>
      </form>

      <figure class="preview">
        <QrPoster :content="content" :ink="ink" :size="size" />
        <figcaption class="muted">{{ t('events.qrPoster.preview') }}</figcaption>
      </figure>
    </div>
  </section>
</template>

<style scoped>
.back {
  justify-self: start;
}

h1 {
  margin: 0;
}

.intro {
  max-width: 40rem;
  margin: 0;
}

.layout {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 2rem;
}

.controls {
  flex: 1 1 20rem;
  min-width: 0;
}

.choices {
  display: grid;
  gap: 0.25rem;
  margin: 0;
  padding: 0;
  border: 0;
}

.choices--inline {
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 9rem), 1fr));
}

.choices legend {
  margin-bottom: 0.4rem;
  font-weight: 700;
}

.choice {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  min-height: 2.75rem;
}

.choice input {
  flex: none;
  width: 1.25rem;
  height: 1.25rem;
  accent-color: var(--color-accent);
}

.buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.link-button {
  display: inline-flex;
  align-items: center;
  min-height: 2.75rem;
  padding: 0 1.2rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  color: var(--color-text);
  font-weight: 700;
  text-decoration: none;
}

.hint {
  margin: 0;
  font-size: 0.9rem;
}

.preview {
  display: grid;
  flex: 0 1 22rem;
  gap: 0.6rem;
  justify-items: center;
  width: min(100%, 22rem);
  margin: 0 auto;
}

.preview :deep(.qr-poster) {
  border-radius: 0.25rem;
  box-shadow: 0 1rem 2.5rem rgb(0 0 0 / 35%);
}
</style>
