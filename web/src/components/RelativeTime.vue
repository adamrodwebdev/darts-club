<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatDateTime, formatRelative } from '@/i18n/format'

const props = defineProps<{ date: Date }>()
const { t } = useI18n()

const label = computed(() => formatRelative(props.date) ?? t('common.justNow'))
</script>

<template>
  <time :datetime="props.date.toISOString()" :title="formatDateTime(props.date)">{{ label }}</time>
</template>
