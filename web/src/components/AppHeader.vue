<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useI18n } from 'vue-i18n'
import { LANDING } from '@/content/landing'
import { HOME_PATHS } from '@/i18n/locales'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notifications'
import { usePreferencesStore } from '@/stores/preferences'
import LanguageToggle from './preferences/LanguageToggle.vue'
import ThemeToggle from './preferences/ThemeToggle.vue'
import UserAvatar from './UserAvatar.vue'

const auth = useAuthStore()
const { user } = storeToRefs(auth)
const { unread } = storeToRefs(useNotificationStore())
const { t } = useI18n()
const preferences = usePreferencesStore()
</script>

<template>
  <header class="header">
    <a class="skip-link" href="#main">{{ t('nav.skip') }}</a>
    <nav class="header__inner" :aria-label="t('nav.main')">
      <RouterLink :to="HOME_PATHS[preferences.locale]" class="brand" :aria-label="t('nav.brandHome')">
        <img class="brand__logo" :src="LANDING.logo" width="38" height="38" alt="" />

        <span class="brand__name" :class="{ 'brand__name--member': user }">Skull Darts 71</span>
      </RouterLink>

      <ul v-if="user" class="nav">
        <!-- Liens du bureau : en-tête à partir de 768 px, sinon dans « Mon compte » -->
        <li v-if="user.canAdminister" class="nav__item--board">
          <RouterLink to="/admin" class="nav__link" :aria-label="t('nav.president')">
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M12 3l2.5 5 5.5.8-4 3.9.9 5.5L12 15.6 7.1 18.2l.9-5.5-4-3.9 5.5-.8z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /></svg>
            <span class="nav__label">{{ t('nav.president') }}</span>
          </RouterLink>
        </li>
        <li v-if="user.canManageTreasury" class="nav__item--board">
          <RouterLink to="/tresorerie" class="nav__link" :aria-label="t('nav.treasury')">
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M3 7h18v12H3zM3 7l2-3h14l2 3M12 11v4M9 13h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /></svg>
            <span class="nav__label">{{ t('nav.treasury') }}</span>
          </RouterLink>
        </li>
        <li>
          <RouterLink to="/evenements" class="nav__link" :aria-label="t('nav.events')">
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M4 6h16v14H4zM4 10h16M8 3v5M16 3v5" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /></svg>
            <span class="nav__label">{{ t('nav.events') }}</span>
          </RouterLink>
        </li>
        <li>
          <RouterLink to="/infos" class="nav__link" :aria-label="t('nav.infosLong')">
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M4 5h12v14H6a2 2 0 0 1-2-2zM16 9h4v8a2 2 0 0 1-4 0M7 9h6M7 13h6" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /></svg>
            <span class="nav__label">{{ t('nav.infos') }}</span>
          </RouterLink>
        </li>
        <li>
          <RouterLink to="/messages" class="nav__link">
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M4 5h16v11H8l-4 4z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /></svg>
            <span class="nav__label">{{ t('nav.messages') }}</span>
          </RouterLink>
        </li>
        <li>
          <RouterLink to="/notifications" class="nav__link" :aria-label="t('nav.notificationsUnread', unread)">
            <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M6 17V11a6 6 0 0 1 12 0v6l2 2H4zM10 21h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /></svg>
            <span class="nav__label" aria-hidden="true">{{ t('nav.notifications') }}</span>
            <span v-if="unread > 0" class="badge" aria-hidden="true">{{ unread > 99 ? '99+' : unread }}</span>
          </RouterLink>
        </li>
        <li>
          <RouterLink to="/compte" class="nav__link nav__link--account" :aria-label="t('nav.account')">
            <UserAvatar :name="user.displayName" :src="user.avatarUrl" :size="32" />
            <span class="nav__label">{{ user.displayName }}</span>
          </RouterLink>
        </li>
      </ul>
      <div class="prefs" :class="{ 'prefs--member': user }">
        <LanguageToggle />
        <ThemeToggle />
      </div>
      <RouterLink v-if="!user" to="/connexion" class="login-link">{{ t('nav.login') }}</RouterLink>
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
  gap: 0.5rem;
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
  margin-right: auto;
}

.brand__name {
  white-space: nowrap;
}

.brand__logo {
  flex: none;
  width: 38px;
  height: 38px;
  border-radius: 50%;
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

.nav__item--board {
  display: none;
}

.prefs {
  display: flex;
}

/* Connecté sur mobile : préférences dans « Mon compte », la barre reste sur une ligne */
.prefs--member {
  display: none;
}

@media (min-width: 768px) {
  .nav__item--board {
    display: list-item;
  }

  .prefs--member {
    display: flex;
  }
}

/* Libellés visibles seulement quand toute la barre tient sur une ligne */
@media (min-width: 1100px) {
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

  /* Libellés + bureau + préférences : l'écusson seul laisse la place au menu */
  .brand__name--member {
    display: none;
  }
}

@media (max-width: 519px) {
  .brand__name {
    display: none;
  }
}

/* Connecté : 5 icônes de 44 px, le logo seul suffit sur petit écran */
@media (max-width: 639px) {
  .brand__name--member {
    display: none;
  }
}
</style>
