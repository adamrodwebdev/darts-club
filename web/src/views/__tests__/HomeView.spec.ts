import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import HomeView from '../HomeView.vue'

describe('HomeView', () => {
  it('invites visitors to join the club', async () => {
    const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:p(.*)*', component: HomeView }] })
    const wrapper = mount(HomeView, { global: { plugins: [createPinia(), router] } })

    expect(wrapper.get('h1').text()).toBe('Skull Darts 71')
    expect(wrapper.get('a').text()).toBe('Rejoindre le club')
  })
})
