<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { onBeforeRouteLeave, RouterLink, type RouteLocationRaw } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import DocumentFields from '@/components/documents/DocumentFields.vue'
import DocumentSheet from '@/components/documents/DocumentSheet.vue'
import ProcedureStatusPill from '@/components/documents/ProcedureStatusPill.vue'
import TicketSheet from '@/components/documents/TicketSheet.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { CLUB_TIME_ZONE, formatDate, formatDateTime } from '@/i18n/format'
import { dayDate, PROCEDURE_STATUSES, type DocumentContent, type DocumentType, type ProcedureDocument, type ProcedureStatus } from '@/models/EventDocuments'
import { printOnly } from '@/print/printOnly'
import { useServices } from '@/services'
import type { DocumentDraft } from '@/services/EventDocumentsService'

const props = defineProps<{ id: string; type: DocumentType }>()
const services = useServices()
const { t, locale } = useI18n()

/** Démarche telle qu'enregistrée (avancement, auteur) et document affiché (changements en cours compris) */
const procedure = ref<ProcedureDocument | null>(null)
const content = ref<DocumentContent | null>(null)
const values = ref<Record<string, string | boolean>>({})
const rewrites = ref<Record<string, string>>({})
const savedSnapshot = ref('')
const justSaved = ref(false)
const previewFailed = ref(false)
const printing = ref(false)

const load = useAsyncAction()
const save = useAsyncAction()
const progress = useAsyncAction()

/** Lien utile à côté de certains documents (le budget vient du bilan, l'attestation de la fiche) */
const RELATED: Partial<Record<DocumentType, { to: RouteLocationRaw; label: string }>> = {
  budget: { to: { name: 'event-accounts', params: { id: props.id } }, label: 'events.documents.related.accounts' },
  assurance: { to: { name: 'association-profile' }, label: 'events.documents.related.profile' },
}
const related = computed(() => RELATED[props.type] ?? null)

function sorted(record: Record<string, unknown>): [string, unknown][] {
  return Object.entries(record).sort(([a], [b]) => a.localeCompare(b))
}

function snapshot(): string {
  return JSON.stringify([sorted(values.value), sorted(rewrites.value)])
}

const dirty = computed(() => procedure.value !== null && snapshot() !== savedSnapshot.value)

function draft(): DocumentDraft {
  return { values: { ...values.value }, rewrites: { ...rewrites.value } }
}

/** Reprend une démarche enregistrée : ses champs et ses passages réécrits deviennent le brouillon */
function adopt(document: ProcedureDocument): void {
  procedure.value = document
  content.value = document.content
  values.value = Object.fromEntries(document.content.fields.map((field) => [field.key, field.value]))
  rewrites.value = Object.fromEntries(document.content.blocks.filter((block) => block.rewritten).map((block) => [block.id, block.text]))
  savedSnapshot.value = snapshot()
}

onMounted(async () => {
  window.addEventListener('beforeunload', warnBeforeUnload)
  const loaded = await load.run(() => services.eventDocuments.get(props.id, props.type))
  if (loaded) adopt(loaded)
})

/** Aperçu rendu par l'API après une pause dans la saisie ; seule la dernière réponse compte */
let timer: ReturnType<typeof setTimeout> | undefined
let lastPreview = 0

function schedulePreview(): void {
  justSaved.value = false
  clearTimeout(timer)
  timer = setTimeout(() => void refresh(), 400)
}

async function refresh(): Promise<void> {
  clearTimeout(timer)
  const ticket = ++lastPreview
  try {
    const next = await services.eventDocuments.preview(props.id, props.type, draft())
    if (ticket !== lastPreview) return
    content.value = next
    previewFailed.value = false
  } catch {
    if (ticket === lastPreview) previewFailed.value = true
  }
}

function updateField(key: string, value: string | boolean): void {
  values.value = { ...values.value, [key]: value }
  schedulePreview()
}

function rewrite(id: string, text: string): void {
  rewrites.value = { ...rewrites.value, [id]: text }
  schedulePreview()
}

function restore(id: string): void {
  rewrites.value = Object.fromEntries(Object.entries(rewrites.value).filter(([key]) => key !== id))
  justSaved.value = false
  void refresh()
}

async function saveDocument(): Promise<void> {
  clearTimeout(timer)
  const saved = await save.run(() => services.eventDocuments.save(props.id, props.type, draft()))
  if (!saved) return
  adopt(saved)
  justSaved.value = true
}

/** L'avancement s'enregistre seul ; les changements en cours du document restent à enregistrer */
async function changeStatus(status: ProcedureStatus): Promise<void> {
  const updated = await progress.run(() => services.eventDocuments.changeStatus(props.id, props.type, status))
  if (!updated) return
  if (dirty.value) procedure.value = updated
  else adopt(updated)
}

/** Courrier ou fiche : la feuille seule, en A4 ; billets : toute la série, 12 par page */
async function print(): Promise<void> {
  if (!content.value?.tickets) {
    printOnly('document')
    return
  }
  printing.value = true
  await nextTick()
  printOnly('tickets', () => (printing.value = false))
}

function warnBeforeUnload(event: BeforeUnloadEvent): void {
  if (dirty.value) event.preventDefault()
}

onBeforeRouteLeave(() => !dirty.value || window.confirm(t('events.documents.leave')))

onBeforeUnmount(() => {
  clearTimeout(timer)
  window.removeEventListener('beforeunload', warnBeforeUnload)
})

function day(value: string): string {
  return formatDate(dayDate(value), { day: 'numeric', month: 'long', timeZone: CLUB_TIME_ZONE })
}

const name = computed(() => t(`events.documents.names.${props.type}`))
const missing = computed(() => (procedure.value?.missing ?? []).map((code) => t(`events.documents.profile.missing.${code}`)).join(', '))

const ticketSummary = computed(() => {
  const tickets = content.value?.tickets
  if (!tickets) return ''
  return t('events.documents.tickets.summary', { count: tickets.count, first: tickets.first, last: tickets.first + tickets.count - 1, pages: Math.ceil(tickets.count / 12) })
})

const saveState = computed(() => {
  if (dirty.value) return t('events.documents.unsaved')
  return justSaved.value ? t('events.documents.saved') : ''
})
</script>

<template>
  <section class="page stack document-page">
    <RouterLink :to="{ name: 'event-documents', params: { id } }" class="back">
      {{ procedure ? t('events.documents.back', { title: procedure.event.title }) : t('events.documents.title') }}
    </RouterLink>
    <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>

    <template v-if="procedure && content">
      <div class="head">
        <p class="eyebrow">{{ procedure.event.title }} · {{ formatDate(procedure.event.startsAt, { dateStyle: 'long', timeZone: CLUB_TIME_ZONE }) }}</p>
        <h1>{{ name }}</h1>
        <p class="muted sub">
          {{ t('events.documents.recipient', { name: procedure.summary.recipient }) }}
          <template v-if="procedure.summary.deadline"> · {{ t('events.documents.sendBefore', { date: day(procedure.summary.deadline) }) }}</template>
        </p>
        <p v-if="procedure.summary.updatedAt && procedure.updatedBy" class="muted small">
          {{ t('events.documents.updatedBy', { date: formatDateTime(procedure.summary.updatedAt), name: procedure.updatedBy }) }}
        </p>
      </div>

      <section class="card progress" aria-labelledby="progress-title">
        <h2 id="progress-title" class="progress__title">{{ t('events.documents.status') }}</h2>
        <ProcedureStatusPill :status="procedure.summary.status" :sent-on="procedure.summary.sentOn" :received-on="procedure.summary.receivedOn" />
        <div v-if="procedure.mayEdit" class="progress__choices" role="group" :aria-label="t('events.documents.statusChoices')">
          <button
            v-for="status in PROCEDURE_STATUSES"
            :key="status"
            type="button"
            class="choice"
            :aria-pressed="status === procedure.summary.status"
            :disabled="progress.pending.value"
            @click="changeStatus(status)"
          >
            {{ t(`events.documents.statuses.${status}`) }}
          </button>
        </div>
        <p v-if="progress.error.value" class="notice notice--error" role="alert">{{ progress.error.value }}</p>
      </section>

      <p v-if="procedure.missing.length > 0" class="notice">
        {{ t('events.documents.missing', { items: missing }) }}
        <RouterLink :to="{ name: 'association-profile' }">{{ t('events.documents.completeProfile') }}</RouterLink>
      </p>
      <p v-if="locale !== 'fr'" class="muted small">{{ t('events.documents.frenchOnly') }}</p>

      <div class="workspace">
        <form class="card stack fields-card" @submit.prevent="saveDocument">
          <h2>{{ t('events.documents.fieldsTitle') }}</h2>
          <p class="muted small">{{ content.fields.length > 0 ? t('events.documents.fieldsIntro') : t('events.documents.noFields') }}</p>
          <DocumentFields v-if="content.fields.length > 0" :fields="content.fields" :values="values" :editable="procedure.mayEdit" @update="updateField" />
          <p v-if="previewFailed" class="notice notice--error" role="alert">{{ t('events.documents.previewFailed') }}</p>
          <p v-if="save.error.value" class="notice notice--error" role="alert">{{ save.error.value }}</p>
          <div class="buttons">
            <BaseButton v-if="procedure.mayEdit" type="submit" :pending="save.pending.value" :disabled="!dirty">{{ t('events.documents.save') }}</BaseButton>
            <BaseButton variant="ghost" @click="print">{{ content.tickets ? t('events.documents.printTickets') : t('events.documents.print') }}</BaseButton>
          </div>
          <p class="muted small save-state" aria-live="polite">{{ saveState }}</p>
          <RouterLink v-if="related" :to="related.to" class="related">{{ t(related.label) }}</RouterLink>
        </form>

        <figure class="preview">
          <template v-if="content.tickets">
            <TicketSheet :tickets="content.tickets" :all="printing" />
            <figcaption class="muted small">{{ ticketSummary }}</figcaption>
          </template>
          <template v-else>
            <DocumentSheet :blocks="content.sheet" :editable="procedure.mayEdit" @edit="rewrite" @restore="restore" />
            <figcaption class="muted small">{{ procedure.mayEdit ? t('events.documents.previewHint') : t('events.documents.previewReadOnly') }}</figcaption>
          </template>
        </figure>
      </div>

      <div class="columns">
        <section v-if="content.notes.length > 0" class="card" aria-labelledby="notes-title">
          <h2 id="notes-title">{{ t('events.documents.notes') }}</h2>
          <ul class="notes" lang="fr">
            <li v-for="note in content.notes" :key="note.id">{{ note.text }}</li>
          </ul>
        </section>
        <section v-if="content.links.length > 0" class="card" aria-labelledby="links-title">
          <h2 id="links-title">{{ t('events.documents.links') }}</h2>
          <ul class="links">
            <li v-for="link in content.links" :key="link.url">
              <a :href="link.url" target="_blank" rel="noopener noreferrer" lang="fr">
                {{ link.label }}<span class="visually-hidden" :lang="locale">{{ t('events.documents.newTab') }}</span>
              </a>
            </li>
          </ul>
        </section>
      </div>
    </template>
  </section>
</template>

<style scoped>
.back {
  justify-self: start;
}

.head {
  display: grid;
  gap: 0.3rem;
}

.head h1,
.head p {
  margin: 0;
}

.eyebrow {
  color: var(--color-link);
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.small {
  font-size: 0.9rem;
}

.progress {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem 1rem;
}

.progress__title {
  margin: 0;
  font-size: 1rem;
}

.progress__choices {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-left: auto;
}

.choice {
  min-height: 2.75rem;
  padding: 0 0.85rem;
  border: 1px solid var(--color-border);
  border-radius: 999px;
  background: transparent;
  color: var(--color-text);
  font: inherit;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
}

.choice[aria-pressed='true'] {
  border-color: var(--color-accent);
  background: var(--color-accent);
  color: var(--color-on-accent);
}

.progress .notice {
  flex-basis: 100%;
}

.workspace {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 1.5rem;
}

.fields-card {
  flex: 1 1 20rem;
  min-width: 0;
}

.fields-card h2,
.fields-card p {
  margin: 0;
}

.buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
}

.save-state:empty {
  display: none;
}

.related {
  justify-self: start;
  display: inline-flex;
  align-items: center;
  min-height: 2.75rem;
  color: var(--color-link);
  font-weight: 600;
}

.preview {
  flex: 2 1 28rem;
  min-width: 0;
  display: grid;
  gap: 0.6rem;
  margin: 0;
}

.columns {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 22rem), 1fr));
  gap: 1rem;
  align-items: start;
}

.notes,
.links {
  display: grid;
  gap: 0.6rem;
  margin: 0;
  padding-left: 1.2rem;
}

.links a {
  display: inline-flex;
  align-items: center;
  min-height: 2.75rem;
  color: var(--color-link);
}
</style>
