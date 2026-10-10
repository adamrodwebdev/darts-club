<script setup lang="ts">
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, RouterView } from 'vue-router'
import AppFooter from '@/components/AppFooter.vue'
import AppHeader from '@/components/AppHeader.vue'
import { useDocumentHead } from '@/composables/useDocumentHead'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notifications'

const auth = useAuthStore()
const notifications = useNotificationStore()
const { t } = useI18n()
useDocumentHead()

// Cycle de vie de la session : centre de notifications + temps réel
watch(
  () => auth.user?.id,
  async (id) => {
    if (id) {
      await notifications.load().catch(() => undefined)
      await notifications.connectRealtime()
    } else {
      notifications.reset()
    }
  },
)
</script>

<template>
  <AppHeader />
  <i18n-t v-if="auth.user?.twoFactorSetupRequired" keypath="app.twoFactorBanner" tag="p" class="tfa-banner" role="alert" scope="global">
    <template #link>
      <RouterLink to="/compte/securite">{{ t('app.twoFactorLink') }}</RouterLink>
    </template>
  </i18n-t>
  <main id="main" tabindex="-1">
    <RouterView />
  </main>
  <AppFooter />
</template>

<style scoped>
.tfa-banner {
  margin: 0;
  padding: 0.75rem var(--gutter);
  background: var(--color-accent);
  color: var(--color-on-accent);
  text-align: center;
}

.tfa-banner a {
  color: inherit;
  font-weight: 700;
}
</style>
