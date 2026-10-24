<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import RelativeTime from '@/components/RelativeTime.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { auditLabel, type AuditEntry } from '@/models/Admin'
import { useServices } from '@/services'

const services = useServices()

const { t, te } = useI18n()
const entries = ref<AuditEntry[]>([])
const load = useAsyncAction()

onMounted(async () => {
  const list = await load.run(() => services.admin.audit())
  if (list) entries.value = list
})

/** Nom d'un détail dans la langue courante (« reason » → « Motif »), sinon la clé telle quelle */
function detailName(key: string): string {
  return te(`admin.audit.keys.${key}`) ? t(`admin.audit.keys.${key}`) : key
}

/** Valeur d'un détail : oui / non pour une case cochée, telle quelle sinon */
function detailValue(value: unknown): string {
  if (typeof value === 'boolean') return value ? t('admin.audit.yes') : t('admin.audit.no')
  return String(value ?? '—')
}

function details(entry: AuditEntry): string {
  return Object.entries(entry.details)
    .map(([key, value]) => t('admin.audit.detail', { key: detailName(key), value: detailValue(value) }))
    .join(' · ')
}
</script>

<template>
  <div class="stack">
    <p class="muted">{{ t('admin.audit.intro') }}</p>
    <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>
    <ol class="log">
      <li v-for="e in entries" :key="e.id">
        <RelativeTime class="muted" :date="e.at" />
        <span><strong>{{ auditLabel(e.action) }}</strong><template v-if="e.target"> · {{ e.target }}</template></span>
        <span class="muted">{{ t('admin.audit.by', { actor: e.actor }) }}<template v-if="details(e)"> · {{ details(e) }}</template></span>
      </li>
    </ol>
  </div>
</template>

<style scoped>
.log {
  display: grid;
  gap: 0.5rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.log li {
  display: grid;
  gap: 0.1rem;
  padding: 0.6rem 0.9rem;
  border-left: 3px solid var(--color-border);
  overflow-wrap: anywhere;
}
</style>
