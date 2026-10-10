<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import TwoFactorSettings from '@/components/TwoFactorSettings.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { services } from '@/services'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const currentPassword = ref('')
const newPassword = ref('')
const changed = ref(false)
const password = useAsyncAction()

const confirmation = ref('')
const deletePassword = ref('')
const removal = useAsyncAction()

async function changePassword(): Promise<void> {
  changed.value = false
  await password.run(async () => {
    await services.profile.changePassword(currentPassword.value, newPassword.value)
    currentPassword.value = newPassword.value = ''
    changed.value = true
  })
}

async function deleteAccount(): Promise<void> {
  await removal.run(async () => {
    await services.profile.deleteAccount(deletePassword.value)
    auth.forget()
    await router.replace('/')
  })
}
</script>

<template>
  <div class="stack">
    <section v-if="auth.user?.hasPassword" class="card" aria-labelledby="pwd-title">
      <h2 id="pwd-title">Mot de passe</h2>
      <form class="stack" novalidate @submit.prevent="changePassword">
        <BaseField v-model="currentPassword" label="Mot de passe actuel" type="password" autocomplete="current-password" required :error="password.fieldErrors.value.currentPassword" />
        <BaseField v-model="newPassword" label="Nouveau mot de passe" type="password" autocomplete="new-password" required hint="12 caractères minimum." :error="password.fieldErrors.value.newPassword" />
        <p v-if="password.error.value" class="notice notice--error" role="alert">{{ password.error.value }}</p>
        <p v-if="changed" class="notice notice--success" role="status">Mot de passe modifié. Tes autres appareils ont été déconnectés.</p>
        <BaseButton type="submit" :pending="password.pending.value">Modifier</BaseButton>
      </form>
    </section>

    <TwoFactorSettings />

    <section class="card" aria-labelledby="data-title">
      <h2 id="data-title">Mes données</h2>
      <p class="muted">Télécharge une copie de toutes les données que le club conserve sur toi (format JSON).</p>
      <a class="link-button" :href="services.profile.exportUrl()" download>Télécharger mes données</a>
    </section>

    <section class="card card--danger" aria-labelledby="delete-title">
      <h2 id="delete-title">Supprimer mon compte</h2>
      <p class="muted">Action définitive. Tes messages restent visibles des autres sous le nom « Ancien adhérent ».</p>
      <form class="stack" novalidate @submit.prevent="deleteAccount">
        <BaseField v-model="confirmation" label="Tape SUPPRIMER pour confirmer" autocomplete="off" />
        <BaseField v-if="auth.user?.hasPassword" v-model="deletePassword" label="Mot de passe" type="password" autocomplete="current-password" />
        <p v-if="removal.error.value" class="notice notice--error" role="alert">{{ removal.error.value }}</p>
        <BaseButton type="submit" variant="danger" :disabled="confirmation !== 'SUPPRIMER'" :pending="removal.pending.value">
          Supprimer définitivement
        </BaseButton>
      </form>
    </section>
  </div>
</template>

<style scoped>
.card--danger {
  border-color: color-mix(in srgb, var(--color-danger) 50%, var(--color-border));
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
</style>
