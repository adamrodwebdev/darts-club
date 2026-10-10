import { createRouter, createWebHistory, type RouteLocationNormalized } from 'vue-router'
import HomeView from '../views/HomeView.vue'
import { useAuthStore } from '@/stores/auth'

declare module 'vue-router' {
  interface RouteMeta {
    title?: string
    requiresAuth?: boolean
    guestOnly?: boolean
  }
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior: (_to, _from, saved) => saved ?? { top: 0 },
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
        { path: 'securite', name: 'account-security', component: () => import('../views/account/SecurityView.vue'), meta: { title: 'Sécurité' } },
      ],
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
  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: 'account' }
  }
  return true
})

router.afterEach((to) => {
  document.title = to.meta.title ? `${to.meta.title} · Darts Club` : 'Darts Club'
})

export default router
