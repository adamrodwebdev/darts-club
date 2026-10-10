<script setup lang="ts">
import { ref } from 'vue'
import BaseButton from '@/components/BaseButton.vue'
import { useAsyncAction } from '@/composables/useAsyncAction'
import { services } from '@/services'

const feed = ref<{ url: string; webcal: string } | null>(null)
const copied = ref(false)
const action = useAsyncAction()

async function create(): Promise<void> {
  const result = await action.run(() => services.events.createCalendarFeed())
  if (result) {
    feed.value = result
    copied.value = false
  }
}

async function revoke(): Promise<void> {
  if (await action.run(async () => (await services.events.revokeCalendarFeed(), true))) feed.value = null
}

async function copy(): Promise<void> {
  if (!feed.value) return
  await navigator.clipboard.writeText(feed.value.url)
  copied.value = true
}
</script>

<template>
  <section class="card stack" aria-labelledby="calendar-title">
    <h2 id="calendar-title">Calendrier du club dans mon agenda</h2>
    <p class="muted">
      Abonne ton agenda (Google, Apple, Outlook…) aux événements du club : il se met à jour tout seul.
      Le lien est personnel : ne le partage pas. En générer un nouveau désactive l’ancien.
    </p>

    <template v-if="feed">
      <p class="url"><code>{{ feed.url }}</code></p>
      <div class="buttons">
        <BaseButton @click="copy">{{ copied ? 'Lien copié ✓' : 'Copier le lien' }}</BaseButton>
        <a class="link-button" :href="feed.webcal">Ouvrir dans mon agenda</a>
      </div>
    </template>

    <div class="buttons">
      <BaseButton :variant="feed ? 'ghost' : 'primary'" :pending="action.pending.value" @click="create">
        {{ feed ? 'Générer un nouveau lien' : 'Obtenir mon lien d’abonnement' }}
      </BaseButton>
      <BaseButton variant="ghost" :pending="action.pending.value" @click="revoke">Désactiver mon lien</BaseButton>
    </div>
    <p v-if="action.error.value" class="notice notice--error" role="alert">{{ action.error.value }}</p>
  </section>
</template>

<style scoped>
h2 {
  margin: 0;
}

.url code {
  display: block;
  padding: 0.6rem;
  border-radius: var(--radius);
  background: var(--color-surface-2);
  font-family: ui-monospace, 'Cascadia Code', monospace;
  font-size: 0.85rem;
  overflow-wrap: anywhere;
}

.buttons {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.link-button {
  display: inline-flex;
  align-items: center;
  min-height: 2.75rem;
  padding: 0 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  color: var(--color-text);
  font-weight: 700;
  text-decoration: none;
}
</style>
