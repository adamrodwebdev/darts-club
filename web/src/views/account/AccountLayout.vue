<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import BaseButton from '@/components/BaseButton.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const { t } = useI18n()

const tabs = [
  { to: '/compte', key: 'profile' },
  { to: '/compte/cotisation', key: 'membership' },
  { to: '/compte/notifications', key: 'notifications' },
  { to: '/compte/securite', key: 'security' },
] as const

async function logout(): Promise<void> {
  await auth.logout()
  await router.push('/')
}
</script>

<template>
  <section class="page">
    <div class="account__head">
      <h1>{{ t('account.layout.title') }}</h1>
      <BaseButton variant="ghost" @click="logout">{{ t('account.layout.logout') }}</BaseButton>
    </div>

    <!-- Accès bureau (sur mobile, ces liens ne sont pas dans l'en-tête) -->
    <nav v-if="auth.user?.canAdminister || auth.user?.canManageTreasury" class="board-links" :aria-label="t('account.layout.boardNav')">
      <RouterLink v-if="auth.user?.canAdminister" to="/admin">{{ t('account.layout.president') }}</RouterLink>
      <RouterLink v-if="auth.user?.canManageTreasury" to="/tresorerie">{{ t('account.layout.treasury') }}</RouterLink>
    </nav>

    <nav class="tabs" :aria-label="t('account.layout.sectionsNav')">
      <RouterLink v-for="tab in tabs" :key="tab.to" :to="tab.to" class="tabs__link" exact-active-class="tabs__link--active">
        {{ t(`account.layout.tabs.${tab.key}`) }}
      </RouterLink>
    </nav>

    <RouterView />
  </section>
</template>

<style scoped>
.board-links {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.board-links a {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  padding: 0 1rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  color: var(--color-text);
  font-weight: 700;
  text-decoration: none;
}

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
