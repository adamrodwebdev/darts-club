<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notifications'
import UserAvatar from './UserAvatar.vue'

const auth = useAuthStore()
const { user } = storeToRefs(auth)
const { unread } = storeToRefs(useNotificationStore())
</script>

<template>
  <header class="header">
    <a class="skip-link" href="#main">Aller au contenu</a>
    <nav class="header__inner" aria-label="Navigation principale">
      <RouterLink to="/" class="brand" aria-label="Darts Club, accueil">
        <svg viewBox="0 0 32 32" width="28" height="28" aria-hidden="true">
          <circle cx="16" cy="16" r="14" fill="none" stroke="currentColor" stroke-width="2.5" />
          <circle cx="16" cy="16" r="8" fill="none" stroke="currentColor" stroke-width="2.5" />
          <circle cx="16" cy="16" r="2.5" fill="var(--color-accent)" />
        </svg>
        <span class="brand__name">Darts Club</span>
      </RouterLink>

      <ul v-if="user" class="nav">
        <li>
          <RouterLink to="/messages" class="nav__link">
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M4 5h16v11H8l-4 4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /></svg>
            <span class="nav__label">Messages</span>
          </RouterLink>
        </li>
        <li>
          <RouterLink to="/notifications" class="nav__link" :aria-label="`Notifications, ${unread} non lue${unread > 1 ? 's' : ''}`">
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M6 17V11a6 6 0 0 1 12 0v6l2 2H4zM10 21h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /></svg>
            <span class="nav__label" aria-hidden="true">Notifications</span>
            <span v-if="unread > 0" class="badge" aria-hidden="true">{{ unread > 99 ? '99+' : unread }}</span>
          </RouterLink>
        </li>
        <li>
          <RouterLink to="/compte" class="nav__link nav__link--account" aria-label="Mon compte">
            <UserAvatar :name="user.displayName" :src="user.avatarUrl" :size="32" />
            <span class="nav__label">{{ user.displayName }}</span>
          </RouterLink>
        </li>
      </ul>
      <RouterLink v-else to="/connexion" class="login-link">Connexion</RouterLink>
    </nav>
  </header>
</template>

<style scoped>
.header {
  position: sticky;
  top: 0;
  z-index: 10;
  background: color-mix(in srgb, var(--color-bg) 85%, transparent);
  backdrop-filter: blur(10px);
  border-bottom: 1px solid var(--color-border);
}

.header__inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  max-width: var(--max-width);
  margin: 0 auto;
  padding: 0.5rem var(--gutter);
}

.brand {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 44px;
  color: var(--color-text);
  font-weight: 800;
  letter-spacing: 0.02em;
  text-decoration: none;
  text-transform: uppercase;
}

.nav {
  display: flex;
  gap: 0.25rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.nav__link {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 44px;
  min-height: 44px;
  padding: 0 0.6rem;
  border-radius: var(--radius);
  color: var(--color-text);
  text-decoration: none;
}

.nav__link:hover,
.nav__link.router-link-active {
  background: var(--color-surface-2);
}

/* Mobile : icônes seules, libellés accessibles via aria-label */
.nav__label {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}

.badge {
  position: absolute;
  top: 4px;
  right: 2px;
  min-width: 1.25rem;
  padding: 0 0.3rem;
  border-radius: 999px;
  background: var(--color-accent);
  color: var(--color-on-accent);
  font-size: 0.7rem;
  font-weight: 800;
  line-height: 1.25rem;
  text-align: center;
}

.login-link {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  padding: 0 1rem;
  border-radius: var(--radius);
  background: var(--color-accent);
  color: var(--color-on-accent);
  font-weight: 700;
  text-decoration: none;
}

@media (min-width: 768px) {
  .nav__label {
    position: static;
    width: auto;
    height: auto;
    overflow: visible;
    clip-path: none;
  }

  .badge {
    position: static;
  }
}

@media (max-width: 359px) {
  .brand__name {
    display: none;
  }
}
</style>
