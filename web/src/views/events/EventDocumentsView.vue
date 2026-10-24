<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import ProcedureStatusPill from '@/components/documents/ProcedureStatusPill.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { CLUB_TIME_ZONE, clubDay, formatDate } from '@/i18n/format'
import { dayDate, type EventProcedures, type ProcedureStatus, type ProcedureSummary } from '@/models/EventDocuments'
import { useServices } from '@/services'

const props = defineProps<{ id: string }>()
const services = useServices()
const { t } = useI18n()

const data = ref<EventProcedures | null>(null)
const load = useAsyncAction()
const today = clubDay(services.clock.now())

onMounted(async () => {
  const loaded = await load.run(() => services.eventDocuments.list(props.id))
  if (loaded) data.value = loaded
})

/** Bouton de chaque démarche selon son avancement (en lecture seule : « Voir ») */
const ACTIONS: Record<ProcedureStatus, { label: 'prepare' | 'open' | 'add'; primary: boolean }> = {
  'a-faire': { label: 'prepare', primary: true },
  prete: { label: 'open', primary: false },
  envoyee: { label: 'open', primary: false },
  recue: { label: 'open', primary: false },
  'pas-necessaire': { label: 'add', primary: false },
}

/** Démarches encore à envoyer : leur date conseillée peut être dépassée */
const PENDING: readonly ProcedureStatus[] = ['a-faire', 'prete']

function day(value: string): string {
  return formatDate(dayDate(value), { day: 'numeric', month: 'long', timeZone: CLUB_TIME_ZONE })
}

function isLate(procedure: ProcedureSummary): boolean {
  return procedure.deadline !== null && PENDING.includes(procedure.status) && procedure.deadline < today
}

function actionLabel(procedure: ProcedureSummary): string {
  return data.value?.mayEdit ? t(`events.documents.actions.${ACTIONS[procedure.status].label}`) : t('events.documents.actions.view')
}

const missing = computed(() => (data.value?.missing ?? []).map((code) => t(`events.documents.profile.missing.${code}`)).join(', '))

const certificate = computed(() => {
  const until = data.value?.certificateValidUntil
  if (!until) return t('events.documents.profile.noCertificate')
  return data.value?.certificateCoversEvent ? t('events.documents.profile.certificateCovers', { date: day(until) }) : t('events.documents.profile.certificateTooShort', { date: day(until) })
})
</script>

<template>
  <section class="page stack documents-page">
    <RouterLink :to="{ name: 'event', params: { id } }" class="back">{{ data ? t('events.documents.backToEvent', { title: data.event.title }) : t('events.detail.back') }}</RouterLink>
    <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>

    <template v-if="data">
      <div class="head">
        <p class="eyebrow">{{ data.event.title }} · {{ formatDate(data.event.startsAt, { dateStyle: 'long', timeZone: CLUB_TIME_ZONE }) }}</p>
        <h1>{{ t('events.documents.title') }}</h1>
        <p class="muted intro">{{ data.mayEdit ? t('events.documents.intro') : t('events.documents.readOnly') }}</p>
      </div>

      <div class="layout">
        <ol class="procedures" :aria-label="t('events.documents.listLabel')">
          <li v-for="procedure in data.procedures" :key="procedure.type" class="procedure" :class="{ 'procedure--idle': procedure.status === 'pas-necessaire' }">
            <div class="procedure__text">
              <strong class="procedure__name">{{ t(`events.documents.names.${procedure.type}`) }}</strong>
              <span class="muted">
                {{ t(`events.documents.summaries.${procedure.type}`) }}
                <template v-if="procedure.deadline"> · {{ t('events.documents.before', { date: day(procedure.deadline) }) }}</template>
              </span>
              <span v-if="isLate(procedure)" class="late">{{ t('events.documents.late') }}</span>
            </div>
            <ProcedureStatusPill :status="procedure.status" :sent-on="procedure.sentOn" :received-on="procedure.receivedOn" />
            <RouterLink
              :to="{ name: 'event-document', params: { id, type: procedure.type } }"
              class="action"
              :class="{ 'action--primary': data.mayEdit && ACTIONS[procedure.status].primary }"
              :aria-label="t('events.documents.actionLabel', { action: actionLabel(procedure), name: t(`events.documents.names.${procedure.type}`) })"
            >
              {{ actionLabel(procedure) }}
            </RouterLink>
          </li>
        </ol>

        <aside class="aside">
          <section class="card stack-sm" aria-labelledby="profile-title">
            <h2 id="profile-title">{{ t('events.documents.profile.title') }}</h2>
            <p v-if="data.missing.length === 0" class="complete">{{ t('events.documents.profile.complete') }}</p>
            <p v-else class="incomplete">{{ t('events.documents.profile.incomplete', { items: missing }) }}</p>
            <p class="muted small">{{ t('events.documents.profile.text') }}</p>
            <p class="small">{{ certificate }}</p>
            <RouterLink :to="{ name: 'association-profile' }" class="link">{{ data.mayEdit ? t('events.documents.profile.edit') : t('events.documents.profile.view') }}</RouterLink>
          </section>
          <section class="card stack-sm" aria-labelledby="others-title">
            <h2 id="others-title">{{ t('events.documents.others.title') }}</h2>
            <p class="muted small">{{ t('events.documents.others.text') }}</p>
          </section>
        </aside>
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
.intro {
  margin: 0;
}

.eyebrow {
  margin: 0;
  color: var(--color-link);
  font-size: 0.8rem;
  font-weight: 700;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.layout {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: 1.5rem;
}

.procedures {
  flex: 999 1 36rem;
  min-width: 0;
  margin: 0;
  padding: 0;
  list-style: none;
  border: 1px solid var(--color-border);
  border-radius: calc(var(--radius) * 1.5);
  background: var(--color-surface);
}

.procedure {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem 1rem;
  padding: 1rem 1.25rem;
}

.procedure + .procedure {
  border-top: 1px solid var(--color-border);
}

.procedure__text {
  flex: 1 1 16rem;
  display: grid;
  gap: 0.15rem;
}

.procedure--idle .procedure__name {
  color: var(--color-muted);
}

.late {
  color: var(--color-danger);
  font-size: 0.9rem;
  font-weight: 700;
}

.action {
  display: inline-flex;
  align-items: center;
  min-height: 2.75rem;
  padding: 0 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  color: var(--color-text);
  font-weight: 600;
  text-decoration: none;
}

.action--primary {
  border-color: var(--color-accent);
  background: var(--color-accent);
  color: var(--color-on-accent);
  font-weight: 700;
}

.aside {
  flex: 1 1 18rem;
  min-width: 0;
  display: grid;
  gap: 1rem;
}

.stack-sm {
  display: grid;
  gap: 0.5rem;
}

.stack-sm h2,
.stack-sm p {
  margin: 0;
}

.small {
  font-size: 0.9rem;
}

.complete {
  color: var(--color-success);
  font-weight: 700;
}

.incomplete {
  color: var(--color-link);
  font-weight: 700;
}

.link {
  display: inline-flex;
  align-items: center;
  min-height: 2.75rem;
  color: var(--color-link);
  font-weight: 600;
}
</style>
