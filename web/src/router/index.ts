import { createRouter, createWebHistory, type RouteLocationNormalized } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import type { MessageSchema } from '@/i18n'
import { DEFAULT_LOCALE, HOME_PATHS, type Locale } from '@/i18n/locales'
import { useAuthStore } from '@/stores/auth'
import { usePreferencesStore } from '@/stores/preferences'

declare module 'vue-router' {
  interface RouteMeta {
    /** Clé de traduction du titre (section « routes ») */
    titleKey?: keyof MessageSchema['routes']
    /** Page d'accueil d'une langue : l'URL impose la langue */
    homeLocale?: Locale
    requiresAuth?: boolean
    requiresTreasurer?: boolean
    requiresPresident?: boolean
    requiresOrganizer?: boolean
    guestOnly?: boolean
  }
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior: (to, _from, saved) => {
    if (saved) return saved
    if (to.hash) {
      const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches
      return { el: to.hash, top: 72, behavior: reduced ? 'auto' : 'smooth' }
    }
    return { top: 0 }
  },
  routes: [
    { path: HOME_PATHS.fr, name: 'home', component: HomeView, meta: { homeLocale: 'fr' } },
    { path: HOME_PATHS.en, name: 'home-en', component: HomeView, meta: { homeLocale: 'en' } },
    {
      path: '/connexion',
      name: 'login',
      component: () => import('../views/auth/LoginView.vue'),
      meta: { titleKey: 'login', guestOnly: true },
    },
    {
      path: '/inscription',
      name: 'register',
      component: () => import('../views/auth/RegisterView.vue'),
      meta: { titleKey: 'register', guestOnly: true },
    },
    {
      path: '/compte',
      component: () => import('../views/account/AccountLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        { path: '', name: 'account', component: () => import('../views/account/ProfileView.vue'), meta: { titleKey: 'account' } },
        {
          path: 'notifications',
          name: 'account-notifications',
          component: () => import('../views/account/NotificationSettingsView.vue'),
          meta: { titleKey: 'accountNotifications' },
        },
        {
          path: 'cotisation',
          name: 'account-membership',
          component: () => import('../views/account/MembershipView.vue'),
          meta: { titleKey: 'accountMembership' },
        },
        { path: 'securite', name: 'account-security', component: () => import('../views/account/SecurityView.vue'), meta: { titleKey: 'accountSecurity' } },
      ],
    },
    {
      path: '/tresorerie',
      name: 'treasury',
      component: () => import('../views/treasury/TreasuryView.vue'),
      meta: { titleKey: 'treasury', requiresAuth: true, requiresTreasurer: true },
    },
    {
      path: '/admin',
      component: () => import('../views/admin/AdminLayout.vue'),
      meta: { requiresAuth: true, requiresPresident: true },
      children: [
        { path: '', name: 'admin', component: () => import('../views/admin/AdminMembersView.vue'), meta: { titleKey: 'adminMembers' } },
        { path: 'diffusion', name: 'admin-broadcast', component: () => import('../views/admin/BroadcastView.vue'), meta: { titleKey: 'adminBroadcast' } },
        { path: 'annonces', name: 'admin-announcements', component: () => import('../views/admin/AnnouncementsAdminView.vue'), meta: { titleKey: 'adminAnnouncements' } },
        { path: 'journal', name: 'admin-audit', component: () => import('../views/admin/AuditView.vue'), meta: { titleKey: 'adminAudit' } },
      ],
    },
    {
      path: '/evenements',
      name: 'events',
      component: () => import('../views/events/EventsView.vue'),
      meta: { titleKey: 'events', requiresAuth: true },
    },
    {
      path: '/evenements/nouveau',
      name: 'event-create',
      component: () => import('../views/events/EventFormView.vue'),
      meta: { titleKey: 'eventCreate', requiresAuth: true, requiresOrganizer: true },
    },
    {
      path: '/evenements/:id([0-9a-f-]{36})',
      name: 'event',
      component: () => import('../views/events/EventDetailView.vue'),
      props: true,
      meta: { titleKey: 'event', requiresAuth: true },
    },
    {
      path: '/evenements/:id([0-9a-f-]{36})/modifier',
      name: 'event-edit',
      component: () => import('../views/events/EventFormView.vue'),
      props: true,
      meta: { titleKey: 'eventEdit', requiresAuth: true, requiresOrganizer: true },
    },
    {
      path: '/infos',
      name: 'infos',
      component: () => import('../views/InfosView.vue'),
      meta: { titleKey: 'infos', requiresAuth: true },
    },
    {
      path: '/notifications',
      name: 'notifications',
      component: () => import('../views/NotificationsView.vue'),
      meta: { titleKey: 'notifications', requiresAuth: true },
    },
    {
      path: '/messages',
      name: 'messages',
      component: () => import('../views/messages/MessagesView.vue'),
      meta: { titleKey: 'messages', requiresAuth: true },
    },
    {
      path: '/messages/:id([0-9a-f-]{36})',
      name: 'conversation',
      component: () => import('../views/messages/ConversationView.vue'),
      props: true,
      meta: { titleKey: 'conversation', requiresAuth: true },
    },
    {
      path: '/mentions-legales',
      name: 'legal-notice',
      component: () => import('../views/legal/LegalNoticeView.vue'),
      meta: { titleKey: 'legalNotice' },
    },
    {
      path: '/confidentialite',
      name: 'privacy',
      component: () => import('../views/legal/PrivacyView.vue'),
      meta: { titleKey: 'privacy' },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('../views/NotFoundView.vue'),
      meta: { titleKey: 'notFound' },
    },
  ],
})

router.beforeEach(async (to: RouteLocationNormalized) => {
  const preferences = usePreferencesStore()
  const homeLocale = to.meta.homeLocale
  if (homeLocale) {
    // « / » avec une autre langue choisie : accueil de cette langue
    if (homeLocale === DEFAULT_LOCALE && preferences.locale !== DEFAULT_LOCALE) {
      return { path: HOME_PATHS[preferences.locale], hash: to.hash }
    }
    if (homeLocale !== preferences.locale) await preferences.setLocale(homeLocale, false)
  }

  const auth = useAuthStore()
  await auth.init().catch(() => undefined)

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  // Contrôle d'ergonomie : l'API reste seule garante des droits
  if (
    (to.meta.requiresTreasurer && !auth.user?.canManageTreasury) ||
    (to.meta.requiresPresident && !auth.user?.canAdminister) ||
    (to.meta.requiresOrganizer && !auth.user?.canOrganizeEvents)
  ) {
    return { name: 'account' }
  }
  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'account' }
  }
  return true
})


export default router
