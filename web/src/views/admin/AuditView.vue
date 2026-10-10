<script setup lang="ts">
import { onMounted, ref } from 'vue'
import RelativeTime from '@/components/RelativeTime.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { AUDIT_LABELS, type AuditEntry } from '@/models/Admin'
import { services } from '@/services'

const entries = ref<AuditEntry[]>([])
const load = useAsyncAction()

onMounted(async () => {
  const list = await load.run(() => services.admin.audit())
  if (list) entries.value = list
})

function details(entry: AuditEntry): string {
  return Object.entries(entry.details)
    .map(([key, value]) => `${key} : ${String(value ?? '—')}`)
    .join(' · ')
}
</script>

<template>
  <div class="stack">
    <p class="muted">Toutes les actions de la présidence et de l’administration sont enregistrées ici, sans possibilité de modification.</p>
    <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>
    <ol class="log">
      <li v-for="e in entries" :key="e.id">
        <RelativeTime class="muted" :date="e.at" />
        <span><strong>{{ AUDIT_LABELS[e.action] ?? e.action }}</strong><template v-if="e.target"> · {{ e.target }}</template></span>
        <span class="muted">par {{ e.actor }}<template v-if="details(e)"> · {{ details(e) }}</template></span>
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
