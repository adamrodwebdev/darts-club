<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { RouterLink, RouterView } from 'vue-router'

const { t } = useI18n()

const tabs = [
  { to: '/admin', label: 'routes.adminMembers' },
  { to: '/admin/diffusion', label: 'routes.adminBroadcast' },
  { to: '/admin/annonces', label: 'routes.adminAnnouncements' },
  { to: '/admin/journal', label: 'routes.adminAudit' },
] as const
</script>

<template>
  <section class="page">
    <h1>{{ t('nav.president') }}</h1>
    <nav class="tabs" :aria-label="t('admin.layout.nav')">
      <RouterLink v-for="tab in tabs" :key="tab.to" :to="tab.to" class="tabs__link" exact-active-class="tabs__link--active">
        {{ t(tab.label) }}
      </RouterLink>
    </nav>
    <RouterView />
  </section>
</template>

<style scoped>
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
