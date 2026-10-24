<script setup lang="ts">
type IconName = 'fingerprint' | 'shield' | 'phone' | 'computer' | 'key' | 'check' | 'info'

/** Pictogrammes au trait (24 × 24, couleur du texte) des écrans de sécurité */
const ICONS: Readonly<Record<IconName, string>> = {
  fingerprint:
    'M6.5 18.5c1.2-1.8 1.8-4 1.8-6.5a3.7 3.7 0 0 1 7.4 0c0 1.2-.1 2.4-.3 3.5M12 12c0 3.3-.8 6.2-2.4 8.6M15.2 18.8c-.3.9-.7 1.7-1.1 2.5M4.4 15.2c.4-1 .6-2.1.6-3.2a7 7 0 0 1 12.4-4.4M19.4 10.5c.4 1.7.4 3.6.1 5.5',
  shield: 'M12 3l7 3v5c0 4.5-3 8.3-7 10-4-1.7-7-5.5-7-10V6zM9 12l2 2 4-4',
  phone: 'M9 2.5h6a2 2 0 0 1 2 2v15a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2v-15a2 2 0 0 1 2-2zM11 18.5h2',
  computer: 'M4.5 4.5h15A1.5 1.5 0 0 1 21 6v9a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 15V6a1.5 1.5 0 0 1 1.5-1.5zM8 20h8M12 16.5V20',
  key: 'M8 11a4 4 0 1 0 0 8 4 4 0 0 0 0-8zM10.8 12.2 20 3M16.5 6.5 19 9M14 9l2 2',
  check: 'M5 12.5l4.5 4.5L19 7.5',
  info: 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zM12 11v5M12 8h.01',
}

withDefaults(defineProps<{ name: IconName; size?: number }>(), { size: 22 })
</script>

<template>
  <svg class="line-icon" viewBox="0 0 24 24" :width="size" :height="size" aria-hidden="true">
    <path :d="ICONS[name]" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
  </svg>
</template>

<style scoped>
.line-icon {
  flex: none;
}
</style>
