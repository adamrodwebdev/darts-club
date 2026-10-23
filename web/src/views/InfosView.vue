<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import RelativeTime from '@/components/RelativeTime.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import type { Announcement } from '@/models/Admin'
import { useServices } from '@/services'
import { useNotificationStore } from '@/stores/notifications'

const services = useServices()

const { t } = useI18n()
const announcements = ref<Announcement[]>([])
const load = useAsyncAction()

async function refresh(): Promise<void> {
  const items = await load.run(() => services.admin.announcements())
  if (items) announcements.value = items
}

// Nouvelle annonce publiée : le fil se met à jour en temps réel
const stop = useNotificationStore().onNotification((n) => {
  if (n.category === 'information') refresh()
})
onMounted(refresh)
onUnmounted(stop)
</script>

<template>
  <section class="page">
    <h1>{{ t('account.infos.title') }}</h1>
    <p v-if="load.error.value" class="notice notice--error" role="alert">{{ load.error.value }}</p>
    <p v-else-if="!load.pending.value && !announcements.length" class="muted">{{ t('account.infos.empty') }}</p>

    <div class="feed" aria-live="polite">
      <article v-for="a in announcements" :key="a.id" class="card announcement" :class="{ 'announcement--pinned': a.pinned }">
        <p v-if="a.pinned" class="pin">{{ t('account.infos.pinned') }}</p>
        <h2>{{ a.title }}</h2>
        <!-- Texte brut, sauts de ligne conservés -->
        <p class="body">{{ a.body }}</p>
        <p class="meta muted">
          {{ a.author }} · <RelativeTime :date="a.publishedAt" /><template v-if="a.editedAt"> · {{ t('account.infos.edited') }}</template>
        </p>
      </article>
    </div>
  </section>
</template>

<style scoped>
.feed {
  display: grid;
  gap: 1rem;
  max-width: 48rem;
}

.announcement h2 {
  margin: 0 0 0.5rem;
}

.announcement--pinned {
  border-color: var(--badge-gold);
}

.pin {
  margin: 0 0 0.25rem;
  color: var(--badge-gold);
  font-size: 0.8rem;
  font-weight: 800;
  text-transform: uppercase;
}

.body {
  margin: 0;
  white-space: pre-wrap;
}

.meta {
  margin: 0.75rem 0 0;
  font-size: 0.85rem;
}
</style>
