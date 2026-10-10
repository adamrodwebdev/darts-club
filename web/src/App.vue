<script setup lang="ts">
import { watch } from 'vue'
import { RouterLink, RouterView } from 'vue-router'
import AppFooter from '@/components/AppFooter.vue'
import AppHeader from '@/components/AppHeader.vue'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notifications'

const auth = useAuthStore()
const notifications = useNotificationStore()

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
  <p v-if="auth.user?.twoFactorSetupRequired" class="tfa-banner" role="alert">
    Membre du bureau : <RouterLink to="/compte/securite">active la double authentification</RouterLink> pour accéder aux fonctions du club.
  </p>
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
