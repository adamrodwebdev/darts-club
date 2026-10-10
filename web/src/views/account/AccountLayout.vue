<script setup lang="ts">
import { RouterLink, RouterView, useRouter } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const tabs = [
  { to: '/compte', label: 'Profil' },
  { to: '/compte/notifications', label: 'Notifications' },
  { to: '/compte/securite', label: 'Sécurité' },
]

async function logout(): Promise<void> {
  await auth.logout()
  await router.push('/')
}
</script>

<template>
  <section class="page">
    <div class="account__head">
      <h1>Mon compte</h1>
      <BaseButton variant="ghost" @click="logout">Se déconnecter</BaseButton>
    </div>

    <nav class="tabs" aria-label="Sections du compte">
      <RouterLink v-for="tab in tabs" :key="tab.to" :to="tab.to" class="tabs__link" exact-active-class="tabs__link--active">
        {{ tab.label }}
      </RouterLink>
    </nav>

    <RouterView />
  </section>
</template>

<style scoped>
.account__head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.tabs {
  display: flex;
  gap: 0.25rem;
  margin: 1rem 0 1.5rem;
  overflow-x: auto;
  border-bottom: 1px solid var(--color-border);
}

.tabs__link {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  padding: 0 1rem;
  border-bottom: 3px solid transparent;
  color: var(--color-muted);
  font-weight: 600;
  text-decoration: none;
  white-space: nowrap;
}

.tabs__link--active {
  border-bottom-color: var(--color-accent);
  color: var(--color-text);
}
</style>
