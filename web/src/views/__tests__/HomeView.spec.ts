import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import HomeView from '../HomeView.vue'

describe('HomeView', () => {
  it('renders the club name as main heading', () => {
    const wrapper = mount(HomeView)
    expect(wrapper.get('h1').text()).toBe('Darts Club')
  })
})
