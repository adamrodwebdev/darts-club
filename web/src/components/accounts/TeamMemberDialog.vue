<script setup lang="ts">
import { TEXT_LIMITS } from '@/models/limits'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/BaseButton.vue'
import BaseDialog from '@/components/BaseDialog.vue'
import BaseField from '@/components/BaseField.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import type { Member } from '@/models/Conversation'
import type { Team, TeamMember } from '@/models/EventAccounts'
import { useServices } from '@/services'
import { TEAM_LABELS } from './teamLabels'

const props = defineProps<{
  team: Team
  /** Déjà dans l'équipe : proposés mais pas cliquables */
  members: readonly TeamMember[]
  pending: boolean
  error: string | null
}>()
const emit = defineEmits<{ add: [userId: string]; close: [] }>()
const { t } = useI18n()
const services = useServices()

/** Recherche lancée à partir de deux lettres, après une courte pause de frappe */
const MIN_QUERY = 2
const PAUSE_MS = 250

const search = ref('')
const results = ref<Member[] | null>(null)
let timer: ReturnType<typeof setTimeout> | undefined

watch(search, (query) => {
  clearTimeout(timer)
  if (query.trim().length < MIN_QUERY) {
    results.value = null
    return
  }
  timer = setTimeout(async () => {
    results.value = await services.messaging.members(query.trim()).catch(() => [])
  }, PAUSE_MS)
})
onBeforeUnmount(() => clearTimeout(timer))

const teamName = computed(() => t(TEAM_LABELS[props.team]))
const assigned = computed(() => new Set(props.members.map((member) => member.id)))
const choices = computed(() => (results.value ?? []).filter((member): member is Member & { id: string } => member.id !== null))
/** Annoncé aux lecteurs d'écran à chaque recherche */
const announcement = computed(() => (results.value === null ? '' : t('events.accounts.teams.found', { n: choices.value.length }, choices.value.length)))
</script>

<template>
  <BaseDialog :title="t('events.accounts.teams.dialogTitle', { team: teamName })" @close="emit('close')">
    <div class="stack">
      <BaseField :maxlength="TEXT_LIMITS.search" v-model="search" :label="t('events.accounts.teams.search')" :hint="t('events.accounts.teams.searchHint')" />
      <p class="visually-hidden" aria-live="polite">{{ announcement }}</p>
      <p v-if="results !== null && !choices.length" class="muted">{{ t('events.accounts.teams.noResult') }}</p>
      <ul v-if="choices.length" class="results" :aria-label="t('events.accounts.teams.results')">
        <li v-for="member in choices" :key="member.id">
          <button type="button" class="result" :disabled="pending || assigned.has(member.id)" @click="emit('add', member.id)">
            <UserAvatar :name="member.displayName" :src="member.avatarUrl" :size="32" />
            <span class="result__name">{{ member.displayName }}</span>
            <span v-if="assigned.has(member.id)" class="muted result__note">{{ t('events.accounts.teams.already') }}</span>
          </button>
        </li>
      </ul>
      <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>
      <div class="buttons">
        <BaseButton variant="ghost" @click="emit('close')">{{ t('common.close') }}</BaseButton>
      </div>
    </div>
  </BaseDialog>
</template>

<style scoped>
.results {
  display: grid;
  gap: 0.35rem;
  max-height: min(18rem, 45svh);
  margin: 0;
  padding: 0;
  overflow-y: auto;
  list-style: none;
}

.result {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  width: 100%;
  min-height: 2.75rem;
  padding: 0.3rem 0.6rem;
  border: 0;
  border-radius: var(--radius);
  background: var(--color-surface-2);
  color: var(--color-text);
  font: inherit;
  text-align: start;
  cursor: pointer;
}

.result:disabled {
  cursor: default;
  opacity: 0.7;
}

.result__name {
  flex: 1 1 auto;
  min-width: 0;
  font-weight: 600;
}

.result__note {
  font-size: 0.85rem;
}

.buttons {
  display: flex;
  justify-content: flex-end;
}
</style>
