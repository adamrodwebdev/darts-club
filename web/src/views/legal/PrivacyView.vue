<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import EnContent from './en/PrivacyContent.vue'
import FrContent from './fr/PrivacyContent.vue'

/** Texte rédigé par langue ; la version française fait foi. */
const { locale } = useI18n()
</script>

<template>
  <article class="page legal">
    <EnContent v-if="locale === 'en'" />
    <FrContent v-else />
  </article>
</template>

<style scoped>
.legal {
  max-width: 48rem;
}

.legal :deep(h2) {
  margin-top: 2rem;
}
</style>
