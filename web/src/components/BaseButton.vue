<script setup lang="ts">
withDefaults(
  defineProps<{
    type?: 'button' | 'submit'
    variant?: 'primary' | 'ghost' | 'danger'
    pending?: boolean
    disabled?: boolean
  }>(),
  { type: 'button', variant: 'primary', pending: false, disabled: false },
)
</script>

<template>
  <button
    :type="type"
    class="btn"
    :class="`btn--${variant}`"
    :disabled="disabled || pending"
    :aria-busy="pending"
  >
    <span v-if="pending" class="btn__spinner" aria-hidden="true" />
    <slot />
  </button>
</template>

<style scoped>
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  min-height: 2.75rem;
  min-width: 2.75rem;
  padding: 0.6rem 1.2rem;
  border: 1px solid transparent;
  border-radius: var(--radius);
  font: inherit;
  font-weight: 700;
  cursor: pointer;
  transition:
    transform 0.15s ease,
    background-color 0.15s ease;
}

.btn:active:not(:disabled) {
  transform: scale(0.97);
}

.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn--primary {
  background: var(--color-accent);
  color: var(--color-on-accent);
}

.btn--primary:hover:not(:disabled) {
  background: var(--color-accent-hover);
}

.btn--ghost {
  background: transparent;
  border-color: var(--color-border);
  color: var(--color-text);
}

.btn--danger {
  background: transparent;
  border-color: var(--color-danger);
  color: var(--color-danger);
}

.btn__spinner {
  width: 1em;
  height: 1em;
  border: 2px solid currentColor;
  border-right-color: transparent;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
