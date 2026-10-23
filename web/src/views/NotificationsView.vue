<script setup lang="ts">
import { onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import RelativeTime from '@/components/RelativeTime.vue'
import type { AppNotification } from '@/models/Notification'
import { useNotificationStore } from '@/stores/notifications'

const store = useNotificationStore()
const router = useRouter()
const { t } = useI18n()

onMounted(() => {
  if (!store.loaded) store.load().catch(() => undefined)
})

/** Notification de la liste (proxy réactif) : seuls les membres utilisés sont requis */
async function open(notification: Pick<AppNotification, 'id' | 'read' | 'markRead' | 'link'>): Promise<void> {
  await store.markRead(notification).catch(() => undefined)
  if (notification.link?.startsWith('/')) await router.push(notification.link)
}
</script>

<template>
  <section class="page">
    <div class="head">
      <h1>{{ t('account.notificationCenter.title') }}</h1>
      <BaseButton v-if="store.unread > 0" variant="ghost" @click="store.markAllRead()">{{ t('account.notificationCenter.markAllRead') }}</BaseButton>
    </div>

    <p v-if="store.loaded && store.items.length === 0" class="muted">{{ t('account.notificationCenter.empty') }}</p>

    <ul class="list" aria-live="polite">
      <li v-for="n in store.items" :key="n.id">
        <button type="button" class="item" :class="{ 'item--unread': !n.read }" @click="open(n)">
          <span class="item__label" :data-category="n.category">{{ n.label }}</span>
          <span class="item__title">{{ n.title }}<span v-if="!n.read" class="visually-hidden"> {{ t('account.notificationCenter.unread') }}</span></span>
          <span class="item__body">{{ n.body }}</span>
          <RelativeTime class="item__time" :date="n.createdAt" />
        </button>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.list {
  display: grid;
  gap: 0.5rem;
  margin: 1rem 0 0;
  padding: 0;
  list-style: none;
}

.item {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 0.25rem 0.75rem;
  width: 100%;
  padding: 0.9rem 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-surface);
  color: var(--color-text);
  font: inherit;
  text-align: left;
  cursor: pointer;
}

.item--unread {
  border-left: 4px solid var(--color-accent);
}

.item__label {
  grid-row: span 2;
  align-self: start;
  padding: 0.15rem 0.5rem;
  border-radius: 999px;
  background: var(--color-surface-2);
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
}

.item__label[data-category='annulation'] {
  background: color-mix(in srgb, var(--color-danger) 25%, transparent);
}

.item__title {
  font-weight: 700;
}

.item__body {
  color: var(--color-muted);
  overflow-wrap: anywhere;
}

.item__time {
  grid-column: 2;
  font-size: 0.8rem;
  color: var(--color-muted);
}
</style>
