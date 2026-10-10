<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import OAuthButtons from '@/components/OAuthButtons.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

const email = ref('')
const phone = ref('')
const password = ref('')
const done = ref(false)
const { pending, error, fieldErrors, run } = useAsyncAction()

async function submit(): Promise<void> {
  await run(async () => {
    await auth.register({ email: email.value, password: password.value, phone: phone.value || undefined })
    done.value = true
  })
}
</script>

<template>
  <section class="page page--narrow">
    <h1>Rejoindre le club</h1>

    <div v-if="done" class="notice notice--success" role="status">
      <p><strong>Vérifie ta boîte mail.</strong></p>
      <p>Si l’adresse est valide, tu viens de recevoir un lien pour activer ton compte.</p>
    </div>

    <template v-else>
      <form class="stack" novalidate @submit.prevent="submit">
        <BaseField v-model="email" label="Email" type="email" autocomplete="email" required :error="fieldErrors.email" />
        <BaseField
          v-model="phone"
          label="Téléphone (facultatif)"
          type="tel"
          autocomplete="tel"
          hint="Pour être joint par le bureau, jamais affiché aux autres adhérents."
          :error="fieldErrors.phone"
        />
        <BaseField
          v-model="password"
          label="Mot de passe"
          type="password"
          autocomplete="new-password"
          required
          hint="12 caractères minimum. Une phrase de passe est idéale."
          :error="fieldErrors.password"
        />

        <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

        <BaseButton type="submit" :pending="pending">Créer mon compte</BaseButton>
      </form>

      <OAuthButtons />
    </template>

    <p class="switch">Déjà membre ? <RouterLink to="/connexion">Se connecter</RouterLink></p>
  </section>
</template>

<style scoped>
.switch {
  margin-top: 1.5rem;
  text-align: center;
}
</style>
