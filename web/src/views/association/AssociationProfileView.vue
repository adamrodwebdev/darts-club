<script setup lang="ts">
import { computed, onMounted, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { CLUB_TIME_ZONE, formatDate, formatDateTime } from '@/i18n/format'
import type { AssociationDetails, AssociationProfile } from '@/models/AssociationProfile'
import { dayDate } from '@/models/EventDocuments'
import { useServices } from '@/services'

const services = useServices()
const { t, te } = useI18n()
const id = useId()

const profile = ref<AssociationProfile | null>(null)
const details = ref<AssociationDetails>({
  officialName: '',
  rnaNumber: '',
  siret: '',
  seatAddress: '',
  presidentName: '',
  secretaryName: '',
  treasurerName: '',
  insurerName: '',
  insurancePolicy: '',
})
const certificateFile = ref<File | null>(null)
const validUntil = ref('')
const savedDetails = ref(false)
const savedCertificate = ref(false)

const load = useAsyncAction()
const save = useAsyncAction()
const upload = useAsyncAction()

function adopt(next: AssociationProfile): void {
  profile.value = next
  details.value = { ...next.details }
}

onMounted(async () => {
  const loaded = await load.run(() => services.associationProfile.get())
  if (loaded) adopt(loaded)
})

async function saveDetails(): Promise<void> {
  savedDetails.value = false
  const updated = await save.run(() => services.associationProfile.update({ ...details.value }))
  if (!updated) return
  adopt(updated)
  savedDetails.value = true
}

function pickFile(event: Event): void {
  const input = event.target
  certificateFile.value = input instanceof HTMLInputElement ? (input.files?.[0] ?? null) : null
}

async function sendCertificate(): Promise<void> {
  const file = certificateFile.value
  if (!file) return
  savedCertificate.value = false
  const updated = await upload.run(() => services.associationProfile.replaceCertificate(file, validUntil.value))
  if (!updated) return
  profile.value = updated
  certificateFile.value = null
  validUntil.value = ''
  savedCertificate.value = true
}

/** Champs regroupés comme sur un courrier : l'association, son bureau, son assurance */
const GROUPS = {
  identity: ['officialName', 'rnaNumber', 'siret', 'seatAddress'],
  board: ['presidentName', 'secretaryName', 'treasurerName'],
  insurance: ['insurerName', 'insurancePolicy'],
} as const satisfies Record<string, readonly (keyof AssociationDetails)[]>

/** Champ sur plusieurs lignes (adresse) */
const MULTILINE: readonly (keyof AssociationDetails)[] = ['seatAddress']
const MAX_LENGTH: Record<keyof AssociationDetails, number> = {
  officialName: 120,
  rnaNumber: 20,
  siret: 20,
  seatAddress: 300,
  presidentName: 80,
  secretaryName: 80,
  treasurerName: 80,
  insurerName: 80,
  insurancePolicy: 60,
}

/** Aide sous un champ ou un groupe, quand la traduction en prévoit une */
function help(key: string): string | undefined {
  const path = `events.documents.association.hints.${key}`
  return te(path) ? t(path) : undefined
}

const certificateStatus = computed(() => {
  const certificate = profile.value?.certificate
  if (!certificate) return t('events.documents.association.certificate.none')
  const date = formatDate(dayDate(certificate.validUntil), { dateStyle: 'long', timeZone: CLUB_TIME_ZONE })
  return certificate.valid ? t('events.documents.association.certificate.valid', { date }) : t('events.documents.association.certificate.expired', { date })
})
</script>

<template>
  <section class="page stack association-page">
    <div class="head">
      <h1>{{ t('events.documents.association.title') }}</h1>
      <p class="muted">{{ profile && !profile.mayEdit ? t('events.documents.association.readOnly') : t('events.documents.association.intro') }}</p>
    </div>
    <p class="notice privacy">{{ t('events.documents.association.private') }}</p>
    <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>

    <template v-if="profile">
      <form class="card stack" @submit.prevent="saveDetails">
        <fieldset v-for="(keys, group) in GROUPS" :key="group" class="group" :disabled="!profile.mayEdit">
          <legend>{{ t(`events.documents.association.groups.${group}`) }}</legend>
          <p v-if="help(group)" class="muted small">{{ help(group) }}</p>
          <div class="grid">
            <BaseField
              v-for="key in keys"
              :key="key"
              v-model="details[key]"
              :class="{ wide: MULTILINE.includes(key) }"
              :label="t(`events.documents.association.fields.${key}`)"
              :hint="help(key)"
              :error="save.fieldErrors.value[key]"
              :multiline="MULTILINE.includes(key)"
              :maxlength="MAX_LENGTH[key]"
              :required="key === 'officialName'"
            />
          </div>
        </fieldset>
        <p v-if="save.error.value" class="notice notice--error" role="alert">{{ save.error.value }}</p>
        <p v-if="savedDetails" class="notice notice--success" role="status">{{ t('events.documents.association.saved') }}</p>
        <p v-if="profile.updatedAt && profile.updatedBy" class="muted small">
          {{ t('events.documents.association.updated', { date: formatDateTime(profile.updatedAt), name: profile.updatedBy }) }}
        </p>
        <BaseButton v-if="profile.mayEdit" type="submit" :pending="save.pending.value">{{ t('events.documents.association.save') }}</BaseButton>
      </form>

      <section class="card stack" aria-labelledby="certificate-title">
        <h2 id="certificate-title">{{ t('events.documents.association.certificate.title') }}</h2>
        <p :class="profile.certificate?.valid ? 'valid' : 'muted'">{{ certificateStatus }}</p>
        <a v-if="profile.certificate" class="link" :href="services.associationProfile.certificateUrl()" download>{{ t('events.documents.association.certificate.download') }}</a>

        <form v-if="profile.mayEdit" class="stack" @submit.prevent="sendCertificate">
          <div class="file">
            <label :for="`${id}-file`">{{ t('events.documents.association.certificate.file') }}</label>
            <input :id="`${id}-file`" type="file" accept="application/pdf,.pdf" required @change="pickFile" />
          </div>
          <BaseField v-model="validUntil" type="date" :label="t('events.documents.association.certificate.validUntil')" required />
          <p v-if="upload.error.value" class="notice notice--error" role="alert">{{ upload.error.value }}</p>
          <p v-if="savedCertificate" class="notice notice--success" role="status">{{ t('events.documents.association.certificate.sent') }}</p>
          <BaseButton type="submit" variant="ghost" :pending="upload.pending.value" :disabled="!certificateFile">
            {{ profile.certificate ? t('events.documents.association.certificate.replace') : t('events.documents.association.certificate.add') }}
          </BaseButton>
        </form>
      </section>
    </template>
  </section>
</template>

<style scoped>
.association-page {
  max-width: 48rem;
}

.head {
  display: grid;
  gap: 0.3rem;
}

.head h1,
.head p {
  margin: 0;
}

.privacy {
  font-size: 0.95rem;
}

.group {
  display: grid;
  gap: 0.75rem;
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}

.group legend {
  margin-bottom: 0.5rem;
  padding: 0;
  font-size: 1.05rem;
  font-weight: 800;
}

.group p {
  margin: 0;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(100%, 14rem), 1fr));
  gap: 1rem;
}

.wide {
  grid-column: 1 / -1;
}

.small {
  font-size: 0.9rem;
}

.valid {
  color: var(--color-success);
  font-weight: 700;
}

.card h2,
.card > p {
  margin: 0;
}

.link {
  justify-self: start;
  display: inline-flex;
  align-items: center;
  min-height: 2.75rem;
  color: var(--color-link);
  font-weight: 600;
}

.file {
  display: grid;
  gap: 0.35rem;
}

.file label {
  font-weight: 600;
  font-size: 0.95rem;
}

.file input {
  min-height: 2.75rem;
  font: inherit;
}
</style>
