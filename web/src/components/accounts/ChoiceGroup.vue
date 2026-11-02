<script setup lang="ts" generic="T extends string">
/** Choix exclusif présenté en pastilles (boutons radio natifs : clavier et lecteurs d'écran) */
defineProps<{ legend: string; name: string; options: readonly { value: T; label: string }[] }>()
const model = defineModel<T>({ required: true })
</script>

<template>
  <fieldset class="choices">
    <legend>{{ legend }}</legend>
    <div class="choices__list">
      <label v-for="option in options" :key="option.value" class="choice" :class="{ 'choice--on': model === option.value }">
        <input v-model="model" type="radio" :name="name" :value="option.value" />
        {{ option.label }}
      </label>
    </div>
  </fieldset>
</template>

<style scoped>
.choices {
  display: grid;
  align-content: start;
  gap: 0.4rem;
  min-width: 0;
  margin: 0;
  padding: 0;
  border: 0;
}

legend {
  margin-bottom: 0.4rem;
  padding: 0;
  font-size: 0.9rem;
  font-weight: 600;
}

.choices__list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.choice {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 2.75rem;
  padding: 0 0.9rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  background: var(--color-input);
  cursor: pointer;
}

.choice--on {
  border-color: var(--color-accent);
}

.choice input {
  width: 1.1rem;
  height: 1.1rem;
  margin: 0;
  accent-color: var(--color-accent);
}

.choice:has(input:focus-visible) {
  outline: 2px solid var(--color-link);
  outline-offset: 2px;
}
</style>
