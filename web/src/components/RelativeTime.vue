<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ date: Date }>()

const formatter = new Intl.RelativeTimeFormat('fr', { numeric: 'auto' })
const absolute = new Intl.DateTimeFormat('fr', { dateStyle: 'long', timeStyle: 'short' })

const label = computed(() => {
  const seconds = Math.round((props.date.getTime() - Date.now()) / 1000)
  const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
  ]
  for (const [unit, size] of units) {
    if (Math.abs(seconds) >= size) return formatter.format(Math.round(seconds / size), unit)
  }
  return 'à l’instant'
})
</script>

<template>
  <time :datetime="props.date.toISOString()" :title="absolute.format(props.date)">{{ label }}</time>
</template>
