import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import BaseField from '../BaseField.vue'

/** Champ mot de passe dans un formulaire, comme sur les pages de connexion */
const PasswordForm = defineComponent(() => {
  const value = ref('fl3ch!')
  return () =>
    h('form', { onSubmit: (event: Event) => event.preventDefault() }, [
      h(BaseField, { modelValue: value.value, 'onUpdate:modelValue': (v: string) => (value.value = v), label: 'Mot de passe', type: 'password' }),
    ])
})

describe('BaseField', () => {
  it('shows the password on demand and hides it again when the form is sent', async () => {
    const wrapper = mount(PasswordForm, { attachTo: document.body })
    const input = wrapper.get('input')
    const toggle = wrapper.get('button')
    expect(input.attributes('type')).toBe('password')
    expect(toggle.attributes('aria-pressed')).toBe('false')

    await toggle.trigger('click')
    expect(input.attributes('type')).toBe('text')
    expect(toggle.attributes('aria-pressed')).toBe('true')
    expect(toggle.attributes('aria-controls')).toBe(input.attributes('id'))

    await wrapper.get('form').trigger('submit')
    expect(input.attributes('type')).toBe('password')
    wrapper.unmount()
  })

  it('adds no button to other fields', () => {
    const wrapper = mount(BaseField, { props: { modelValue: '', label: 'Email', type: 'email' } })
    expect(wrapper.find('button').exists()).toBe(false)
  })
})
