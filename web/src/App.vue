<script setup lang="ts">
import { watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import AppFooter from '@/components/AppFooter.vue'
import AppHeader from '@/components/AppHeader.vue'
import { useDocumentHead } from '@/composables/useDocumentHead'
import { HOME_PATHS } from '@/i18n/locales'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notifications'
import { usePreferencesStore } from '@/stores/preferences'

const auth = useAuthStore()
const notifications = useNotificationStore()
const preferences = usePreferencesStore()
const { t } = useI18n()
const route = useRoute()
const router = useRouter()
useDocumentHead()

// Cycle de vie de la session : centre de notifications + temps réel
watch(
  () => auth.user?.id,
  async (id) => {
    if (id) {
      // Langue et thème du compte (choisis éventuellement sur un autre appareil)
      const saved = auth.user?.preferences
      if (saved) {
        await preferences.adoptAccount(saved)
        if (route.meta.homeLocale && route.meta.homeLocale !== preferences.locale) await router.replace(HOME_PATHS[preferences.locale])
      }
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
