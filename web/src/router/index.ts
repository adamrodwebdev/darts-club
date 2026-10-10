import { createRouter, createWebHistory, type RouteLocationNormalized } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import { useAuthStore } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    title?: string
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
    { path: '/', name: 'home', component: HomeView },
    {
      path: '/connexion',
      name: 'login',
      component: () => import('../views/auth/LoginView.vue'),
      meta: { title: 'Connexion', guestOnly: true },
    },
    {
      path: '/inscription',
      name: 'register',
      component: () => import('../views/auth/RegisterView.vue'),
      meta: { title: 'Inscription', guestOnly: true },
    },
    {
      path: '/compte',
      component: () => import('../views/account/AccountLayout.vue'),
      meta: { requiresAuth: true },
      children: [
        { path: '', name: 'account', component: () => import('../views/account/ProfileView.vue'), meta: { title: 'Mon profil' } },
        {
          path: 'notifications',
          name: 'account-notifications',
          component: () => import('../views/account/NotificationSettingsView.vue'),
          meta: { title: 'Mes notifications' },
        },
        {
          path: 'cotisation',
          name: 'account-membership',
          component: () => import('../views/account/MembershipView.vue'),
          meta: { title: 'Ma cotisation' },
        },
        { path: 'securite', name: 'account-security', component: () => import('../views/account/SecurityView.vue'), meta: { title: 'Sécurité' } },
      ],
    },
    {
      path: '/tresorerie',
      name: 'treasury',
      component: () => import('../views/treasury/TreasuryView.vue'),
      meta: { title: 'Trésorerie', requiresAuth: true, requiresTreasurer: true },
    },
    {
      path: '/admin',
      component: () => import('../views/admin/AdminLayout.vue'),
      meta: { requiresAuth: true, requiresPresident: true },
      children: [
        { path: '', name: 'admin', component: () => import('../views/admin/AdminMembersView.vue'), meta: { title: 'Membres' } },
        { path: 'diffusion', name: 'admin-broadcast', component: () => import('../views/admin/BroadcastView.vue'), meta: { title: 'Diffusion' } },
        { path: 'annonces', name: 'admin-announcements', component: () => import('../views/admin/AnnouncementsAdminView.vue'), meta: { title: 'Annonces' } },
        { path: 'journal', name: 'admin-audit', component: () => import('../views/admin/AuditView.vue'), meta: { title: 'Journal' } },
      ],
    },
    {
      path: '/evenements',
      name: 'events',
      component: () => import('../views/events/EventsView.vue'),
      meta: { title: 'Événements', requiresAuth: true },
    },
    {
      path: '/evenements/nouveau',
      name: 'event-create',
      component: () => import('../views/events/EventFormView.vue'),
      meta: { title: 'Nouvel événement', requiresAuth: true, requiresOrganizer: true },
    },
    {
      path: '/evenements/:id([0-9a-f-]{36})',
      name: 'event',
      component: () => import('../views/events/EventDetailView.vue'),
      props: true,
      meta: { title: 'Événement', requiresAuth: true },
    },
    {
      path: '/evenements/:id([0-9a-f-]{36})/modifier',
      name: 'event-edit',
      component: () => import('../views/events/EventFormView.vue'),
      props: true,
      meta: { title: 'Modifier l’événement', requiresAuth: true, requiresOrganizer: true },
    },
    {
      path: '/infos',
      name: 'infos',
      component: () => import('../views/InfosView.vue'),
      meta: { title: 'Infos du club', requiresAuth: true },
    },
    {
      path: '/notifications',
      name: 'notifications',
      component: () => import('../views/NotificationsView.vue'),
      meta: { title: 'Notifications', requiresAuth: true },
    },
    {
      path: '/messages',
      name: 'messages',
      component: () => import('../views/messages/MessagesView.vue'),
      meta: { title: 'Messages', requiresAuth: true },
    },
    {
      path: '/messages/:id([0-9a-f-]{36})',
      name: 'conversation',
      component: () => import('../views/messages/ConversationView.vue'),
      props: true,
      meta: { title: 'Conversation', requiresAuth: true },
    },
    {
      path: '/mentions-legales',
      name: 'legal-notice',
      component: () => import('../views/legal/LegalNoticeView.vue'),
      meta: { title: 'Mentions légales' },
    },
    {
      path: '/confidentialite',
      name: 'privacy',
      component: () => import('../views/legal/PrivacyView.vue'),
      meta: { title: 'Confidentialité' },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: () => import('../views/NotFoundView.vue'),
      meta: { title: 'Page introuvable' },
    },
  ],
})

router.beforeEach(async (to: RouteLocationNormalized) => {
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

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} · Skull Darts 71` : 'Skull Darts 71'
})

export default router
