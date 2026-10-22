<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatDateTime, formatRelative } from '@/i18n/format'
import { useServices } from '@/services'

const props = defineProps<{ date: Date }>()
const { t } = useI18n()
const { clock } = useServices()

const label = computed(() => formatRelative(props.date, clock.now()) ?? t('common.justNow'))
</script>

<template>
  <time :datetime="props.date.toISOString()" :title="formatDateTime(props.date)">{{ label }}</time>
</template>
