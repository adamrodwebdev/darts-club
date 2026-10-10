<script setup lang="ts">
import { RouterLink, RouterView } from 'vue-router'

const tabs = [
  { to: '/admin', label: 'Membres' },
  { to: '/admin/diffusion', label: 'Diffusion' },
  { to: '/admin/annonces', label: 'Annonces' },
  { to: '/admin/journal', label: 'Journal' },
]
</script>

<template>
  <section class="page">
    <h1>Présidence</h1>
    <nav class="tabs" aria-label="Sections de la présidence">
      <RouterLink v-for="tab in tabs" :key="tab.to" :to="tab.to" class="tabs__link" exact-active-class="tabs__link--active">
        {{ tab.label }}
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
