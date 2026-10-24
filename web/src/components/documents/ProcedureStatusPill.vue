<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { CLUB_TIME_ZONE, formatDate } from '@/i18n/format'
import { dayDate, type ProcedureStatus } from '@/models/EventDocuments'

const props = defineProps<{ status: ProcedureStatus; sentOn: string | null; receivedOn: string | null }>()
const { t } = useI18n()

/** Avancements datés : la date notée par l'API accompagne le libellé */
const DATED: Partial<Record<ProcedureStatus, 'sentOn' | 'receivedOn'>> = { envoyee: 'sentOn', recue: 'receivedOn' }

const text = computed(() => {
  const field = DATED[props.status]
  const day = field === undefined ? null : props[field]
  if (day === null) return t(`events.documents.statuses.${props.status}`)
  return t(`events.documents.statusOn.${props.status}`, { date: formatDate(dayDate(day), { day: 'numeric', month: 'long', timeZone: CLUB_TIME_ZONE }) })
})
</script>

<template>
  <span class="status" :class="`status--${status}`">{{ text }}</span>
</template>

<style scoped>
.status {
  display: inline-flex;
  align-items: center;
  padding: 0.25rem 0.75rem;
  border: 1px solid currentColor;
  border-radius: 999px;
  font-size: 0.85rem;
  font-weight: 700;
}

.status--a-faire {
  color: var(--color-link);
}

.status--prete {
  color: var(--badge-blue);
}

.status--envoyee {
  color: var(--badge-gold);
}

.status--recue {
  color: var(--color-success);
}

.status--pas-necessaire {
  border-color: var(--color-border);
  color: var(--color-muted);
}
</style>
