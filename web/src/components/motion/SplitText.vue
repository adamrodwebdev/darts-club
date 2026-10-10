<script setup lang="ts">
import { computed } from 'vue'

/**
 * Texte découpé en mots pour les animations (montée en cascade, reflet mot à mot).
 * Les espaces restent de vrais espaces : lecture d'écran et copier-coller inchangés.
 */
const props = withDefaults(defineProps<{ text: string; tag?: string; rise?: boolean }>(), { tag: 'span', rise: false })

const words = computed(() => props.text.split(/\s+/).filter(Boolean))

/** Espace entre deux mots (aucun après le dernier) */
function gap(index: number): string {
  return index < words.value.length - 1 ? ' ' : ''
}
</script>

<template>
  <component :is="props.tag" class="split" :class="{ 'split--rise': props.rise }">
    <template v-for="(word, i) in words" :key="i">
      <span class="split__word" :style="{ '--i': i }">{{ word }}</span>{{ gap(i) }}
    </template>
  </component>
</template>
