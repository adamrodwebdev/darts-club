<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import OAuthButtons from '@/components/OAuthButtons.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const { t } = useI18n()

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
    <h1>{{ t('auth.register.title') }}</h1>

    <div v-if="done" class="notice notice--success" role="status">
      <p><strong>{{ t('auth.register.checkInbox') }}</strong></p>
      <p>{{ t('auth.register.checkInboxText') }}</p>
    </div>

    <template v-else>
      <form class="stack" novalidate @submit.prevent="submit">
        <BaseField v-model="email" :label="t('common.email')" type="email" autocomplete="email" required :error="fieldErrors.email" />
        <BaseField
          v-model="phone"
          :label="t('auth.register.phone')"
          type="tel"
          autocomplete="tel"
          :hint="t('auth.register.phoneHint')"
          :error="fieldErrors.phone"
        />
        <BaseField
          v-model="password"
          :label="t('common.password')"
          type="password"
          autocomplete="new-password"
          required
          :hint="t('auth.register.passwordHint')"
          :error="fieldErrors.password"
        />

        <p v-if="error" class="notice notice--error" role="alert">{{ error }}</p>

        <BaseButton type="submit" :pending="pending">{{ t('auth.register.submit') }}</BaseButton>
      </form>

      <OAuthButtons />
    </template>

    <p class="switch">{{ t('auth.register.alreadyMember') }} <RouterLink to="/connexion">{{ t('auth.register.login') }}</RouterLink></p>
  </section>
</template>

<style scoped>
.switch {
  margin-top: 1.5rem;
  text-align: center;
}
</style>
