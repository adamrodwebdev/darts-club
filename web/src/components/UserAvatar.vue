<script setup lang="ts">
import { computed } from 'vue'
import { initialsOf } from '@/models/Profile'

const props = withDefaults(defineProps<{ name: string; src?: string | null; size?: number }>(), {
  src: null,
  size: 40,
})

const initials = computed(() => initialsOf(props.name))
</script>

<template>
  <span class="avatar" :style="{ '--size': `${props.size}px` }">
    <img v-if="props.src" :src="props.src" :alt="`Avatar de ${props.name}`" :width="props.size" :height="props.size" loading="lazy" decoding="async" />
    <span v-else aria-hidden="true">{{ initials }}</span>
  </span>
</template>

<style scoped>
.avatar {
  display: inline-grid;
  place-items: center;
  flex: none;
  width: var(--size);
  height: var(--size);
  border-radius: 50%;
  overflow: hidden;
  background: linear-gradient(135deg, var(--color-surface-2), var(--color-border));
  color: var(--color-text);
  font-weight: 700;
  font-size: calc(var(--size) * 0.38);
}

img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>
