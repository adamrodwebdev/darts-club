<script setup lang="ts">
import { watch } from 'vue'
import { RouterView } from 'vue-router'
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
  <main id="main" tabindex="-1">
    <RouterView />
  </main>
</template>
