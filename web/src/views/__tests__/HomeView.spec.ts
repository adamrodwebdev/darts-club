import { describe, it, expect, afterEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import { i18n, loadLocaleMessages } from '@/i18n'
import HomeView from '../HomeView.vue'

function mountHome() {
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:p(.*)*', component: HomeView }] })
  return mount(HomeView, { global: { plugins: [createPinia(), router] } })
}

describe('HomeView', () => {
  afterEach(() => {
    i18n.global.locale.value = 'fr'
    vi.useRealTimers()
  })

  it('invites visitors to join the club', () => {
    const wrapper = mountHome()

    expect(wrapper.get('h1').text()).toBe('Skull Darts 71')
    expect(wrapper.get('a').text()).toBe('Rejoindre le club')
  })

  it('speaks English while keeping the French lyrics', async () => {
    await loadLocaleMessages('en')
    i18n.global.locale.value = 'en'
    vi.useFakeTimers()
    const wrapper = mountHome()
    // Les cordes (dont l'hymne) arrivent une par tâche après le héros
    await vi.runAllTimersAsync()

    expect(wrapper.get('a').text()).toBe('Join the club')
    expect(wrapper.text()).toContain('Verse 1')
    expect(wrapper.get('.lyrics p[lang="fr"]').text()).toContain('La lumière du bar')
  })
})
