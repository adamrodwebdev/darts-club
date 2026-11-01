<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import UserAvatar from '@/components/UserAvatar.vue'
import { TEAMS, type EventAccounts, type Team, type TeamMember } from '@/models/EventAccounts'
import { TEAM_LABELS } from './teamLabels'

defineProps<{ accounts: EventAccounts; pending: boolean }>()
const emit = defineEmits<{ add: [team: Team]; remove: [member: TeamMember] }>()
const { t } = useI18n()
</script>

<template>
  <section class="card stack teams" aria-labelledby="teams-title">
    <h2 id="teams-title">{{ t('events.accounts.teams.title') }}</h2>

    <div v-for="team in TEAMS" :key="team" class="team">
      <h3 class="team__name">{{ t(TEAM_LABELS[team]) }}</h3>
      <ul class="people">
        <li v-for="member in accounts.teams[team]" :key="member.assignmentId" class="person">
          <UserAvatar :name="member.displayName" :src="member.avatarUrl" :size="28" />
          <span class="person__name">{{ member.displayName }}</span>
          <button
            v-if="accounts.menuEditable"
            type="button"
            class="person__remove no-print"
            :disabled="pending"
            :aria-label="t('events.accounts.teams.remove', { name: member.displayName, team: t(TEAM_LABELS[team]) })"
            @click="emit('remove', member)"
          >
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
              <path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
            </svg>
          </button>
        </li>
        <li v-if="!accounts.teams[team].length" class="muted empty">{{ t('events.accounts.teams.empty') }}</li>
        <li v-if="accounts.menuEditable" class="no-print">
          <button
            type="button"
            class="add-button"
            :aria-label="t('events.accounts.teams.addLabel', { team: t(TEAM_LABELS[team]) })"
            @click="emit('add', team)"
          >
            {{ t('events.accounts.teams.add') }}
          </button>
        </li>
      </ul>
    </div>
  </section>
</template>

<style scoped>
.teams h2 {
  margin: 0;
}

.team {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
}

.team__name {
  flex: 0 0 6rem;
  margin: 0;
  font-size: 1rem;
}

.people {
  display: flex;
  flex: 1 1 14rem;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
  margin: 0;
  padding: 0;
  list-style: none;
}

.person {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  min-height: 2.75rem;
  padding-inline-start: 0.35rem;
  border-radius: 999px;
  background: var(--color-surface-2);
}

.person__name {
  font-weight: 600;
}

.person__name:last-child {
  padding-inline-end: 0.9rem;
}

.person__remove {
  display: grid;
  place-items: center;
  width: 2.75rem;
  height: 2.75rem;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--color-muted);
  cursor: pointer;
}

.person__remove:hover {
  color: var(--color-text);
}

.empty {
  font-size: 0.9rem;
}

.add-button {
  min-height: 2.75rem;
  padding: 0 0.9rem;
  border: 1px dashed var(--color-border);
  border-radius: 999px;
  background: transparent;
  color: var(--color-text);
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}
</style>
