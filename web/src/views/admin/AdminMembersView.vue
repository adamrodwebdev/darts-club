<script setup lang="ts">
import { onMounted, ref, watch } from 'vue'
import BaseButton from '@/components/BaseButton.vue'
import BaseDialog from '@/components/BaseDialog.vue'
import BaseField from '@/components/BaseField.vue'
import MemberBadges from '@/components/MemberBadges.vue'
import UserAvatar from '@/components/UserAvatar.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { BOARD_ROLE_OPTIONS, type AdminMember } from '@/models/Admin'
import { services } from '@/services'

const MEMBERSHIP_LABELS: Record<string, string> = { payee: 'Payée', partielle: 'Partielle', impayee: 'Non payée' }

const members = ref<AdminMember[]>([])
const search = ref('')
const info = ref<string | null>(null)
const load = useAsyncAction()
const action = useAsyncAction()

const editing = ref<AdminMember | null>(null)
const draftRoles = ref<string[]>([])
const suspending = ref<AdminMember | null>(null)
const reason = ref('')

async function refresh(): Promise<void> {
  const list = await load.run(() => services.admin.members(search.value))
  if (list) members.value = list
}

let timer: ReturnType<typeof setTimeout> | undefined
watch(search, () => {
  clearTimeout(timer)
  timer = setTimeout(refresh, 250)
})
onMounted(refresh)

function editRoles(member: AdminMember): void {
  editing.value = member
  draftRoles.value = [...member.roles]
}

async function saveRoles(): Promise<void> {
  const member = editing.value
  if (!member) return
  const ok = await action.run(async () => {
    await services.admin.changeRoles(member.id, draftRoles.value)
    return true
  })
  if (ok) {
    info.value = `Rôles de ${member.displayName} mis à jour. Un nouveau membre du bureau devra activer la double authentification.`
    editing.value = null
    await refresh()
  }
}

async function confirmSuspend(): Promise<void> {
  const member = suspending.value
  if (!member) return
  const ok = await action.run(async () => {
    await services.admin.suspend(member.id, reason.value)
    return true
  })
  if (ok) {
    info.value = `${member.displayName} est suspendu(e) et déconnecté(e).`
    suspending.value = null
    reason.value = ''
    await refresh()
  }
}

async function reactivate(member: AdminMember): Promise<void> {
  const ok = await action.run(async () => {
    await services.admin.reactivate(member.id)
    return true
  })
  if (ok) {
    info.value = `${member.displayName} est réactivé(e).`
    await refresh()
  }
}
</script>

<template>
  <div class="stack">
    <BaseField v-model="search" label="Rechercher (pseudo ou email)" />
    <p v-if="info" class="notice notice--success" role="status">{{ info }}</p>
    <p v-if="action.error.value && !editing && !suspending" class="notice notice--error" role="alert">{{ action.error.value }}</p>
    <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>

    <ul class="members">
      <li v-for="m in members" :key="m.id" class="member" :class="{ 'member--suspended': m.suspended }">
        <UserAvatar :name="m.displayName" :src="m.avatarUrl" :size="44" />
        <div class="member__who">
          <strong>{{ m.displayName }}</strong> <MemberBadges :badges="m.badges" compact />
          <span class="muted">{{ m.email }}<template v-if="m.phone"> · {{ m.phone }}</template></span>
          <span class="tags">
            <span v-if="!m.verified" class="tag">Email non vérifié</span>
            <span v-if="m.admin" class="tag">Admin</span>
            <span v-if="m.roles.length && !m.twoFactorEnabled" class="tag tag--warn">2FA à activer</span>
            <span class="tag">Cotisation : {{ m.membership ? MEMBERSHIP_LABELS[m.membership] : '—' }}</span>
            <span v-if="m.suspended" class="tag tag--warn">Suspendu : {{ m.suspensionReason }}</span>
          </span>
        </div>
        <div class="member__actions">
          <BaseButton variant="ghost" :disabled="!m.verified" @click="editRoles(m)">Rôles</BaseButton>
          <BaseButton v-if="m.suspended" variant="ghost" :pending="action.pending.value" @click="reactivate(m)">Réactiver</BaseButton>
          <BaseButton v-else variant="danger" @click="suspending = m">Suspendre</BaseButton>
        </div>
      </li>
    </ul>

    <BaseDialog v-if="editing" :title="`Rôles de ${editing.displayName}`" @close="editing = null">
      <form class="stack" @submit.prevent="saveRoles">
        <fieldset class="roles">
          <legend class="visually-hidden">Rôles du bureau</legend>
          <label v-for="r in BOARD_ROLE_OPTIONS" :key="r.id">
            <input v-model="draftRoles" type="checkbox" :value="r.id" /> {{ r.label }}
          </label>
        </fieldset>
        <p class="muted">Les membres du bureau doivent activer la double authentification.</p>
        <p v-if="action.error.value" class="notice notice--error" role="alert">{{ action.error.value }}</p>
        <div class="buttons">
          <BaseButton variant="ghost" @click="editing = null">Annuler</BaseButton>
          <BaseButton type="submit" :pending="action.pending.value">Enregistrer</BaseButton>
        </div>
      </form>
    </BaseDialog>

    <BaseDialog v-if="suspending" :title="`Suspendre ${suspending.displayName}`" @close="suspending = null">
      <form class="stack" @submit.prevent="confirmSuspend">
        <p class="muted">La personne est déconnectée immédiatement, ne peut plus se connecter ni être contactée. Action réversible et journalisée.</p>
        <BaseField v-model="reason" label="Motif" required :maxlength="200" />
        <p v-if="action.error.value" class="notice notice--error" role="alert">{{ action.error.value }}</p>
        <div class="buttons">
          <BaseButton variant="ghost" @click="suspending = null">Annuler</BaseButton>
          <BaseButton type="submit" variant="danger" :pending="action.pending.value" :disabled="!reason.trim()">Suspendre</BaseButton>
        </div>
      </form>
    </BaseDialog>
  </div>
</template>

<style scoped>
.members {
  display: grid;
  gap: 0.5rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.member {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 0.75rem;
  align-items: start;
  padding: 0.75rem 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-surface);
}

.member--suspended {
  opacity: 0.75;
  border-style: dashed;
}

.member__who {
  display: grid;
  gap: 0.2rem;
  min-width: 0;
  overflow-wrap: anywhere;
}

.member__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  grid-column: 1 / -1;
}

.tags {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem;
}

.tag {
  padding: 0.05rem 0.5rem;
  border-radius: 999px;
  background: var(--color-surface-2);
  font-size: 0.75rem;
}

.tag--warn {
  color: var(--color-danger);
}

.roles {
  display: grid;
  gap: 0.25rem;
  margin: 0;
  padding: 0;
  border: 0;
}

.roles label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 44px;
}

.roles input {
  width: 1.3rem;
  height: 1.3rem;
  accent-color: var(--color-accent);
}

.buttons {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.5rem;
}

@media (min-width: 900px) {
  .member {
    grid-template-columns: auto 1fr auto;
    align-items: center;
  }

  .member__actions {
    grid-column: auto;
  }
}
</style>
