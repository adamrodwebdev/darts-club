<script setup lang="ts">
import { ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import BaseField from '@/components/BaseField.vue'
import PasswordRules from '@/components/PasswordRules.vue'
import TwoFactorSettings from '@/components/TwoFactorSettings.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { useServices } from '@/services'
import { useAuthStore } from '@/stores/auth'

const services = useServices()

const auth = useAuthStore()
const router = useRouter()
const { t } = useI18n()

const currentPassword = ref('')
const newPassword = ref('')
const rulesId = useId()
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
      <h2 id="pwd-title">{{ t('common.password') }}</h2>
      <form class="stack" novalidate @submit.prevent="changePassword">
        <BaseField
          v-model="currentPassword"
          :label="t('account.security.currentPassword')"
          type="password"
          autocomplete="current-password"
          required
          :error="password.fieldErrors.value.currentPassword"
        />
        <BaseField
          v-model="newPassword"
          :label="t('account.security.newPassword')"
          type="password"
          autocomplete="new-password"
          required
          :error="password.fieldErrors.value.newPassword"
          :describedby="rulesId"
        >
          <PasswordRules :id="rulesId" :password="newPassword" />
        </BaseField>
        <p v-if="password.error.value" class="notice notice--error" role="alert">{{ password.error.value }}</p>
        <p v-if="changed" class="notice notice--success" role="status">{{ t('account.security.passwordChanged') }}</p>
        <BaseButton type="submit" :pending="password.pending.value">{{ t('common.edit') }}</BaseButton>
      </form>
    </section>

    <TwoFactorSettings />

    <section class="card" aria-labelledby="data-title">
      <h2 id="data-title">{{ t('account.security.dataTitle') }}</h2>
      <p class="muted">{{ t('account.security.dataText') }}</p>
      <a class="link-button" :href="services.profile.exportUrl()" download>{{ t('account.security.dataDownload') }}</a>
    </section>

    <section class="card card--danger" aria-labelledby="delete-title">
      <h2 id="delete-title">{{ t('account.security.deleteTitle') }}</h2>
      <p class="muted">{{ t('account.security.deleteText') }}</p>
      <form class="stack" novalidate @submit.prevent="deleteAccount">
        <BaseField v-model="confirmation" :label="t('account.security.deleteConfirm', { word: t('account.security.deleteWord') })" autocomplete="off" />
        <BaseField v-if="auth.user?.hasPassword" v-model="deletePassword" :label="t('common.password')" type="password" autocomplete="current-password" />
        <p v-if="removal.error.value" class="notice notice--error" role="alert">{{ removal.error.value }}</p>
        <BaseButton type="submit" variant="danger" :disabled="confirmation !== t('account.security.deleteWord')" :pending="removal.pending.value">
          {{ t('account.security.deleteSubmit') }}
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
