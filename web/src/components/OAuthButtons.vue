<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { services } from '@/services'

const { t } = useI18n()
const providers = [
  { id: 'google', name: 'Google' },
  { id: 'discord', name: 'Discord' },
] as const
</script>

<template>
  <div class="oauth">
    <p class="oauth__sep"><span>{{ t('auth.oauthSeparator') }}</span></p>
    <!-- Liens classiques : la redirection OAuth est une navigation complète -->
    <a v-for="p in providers" :key="p.id" class="oauth__btn" :href="services.auth.oauthUrl(p.id)">{{ t('auth.oauthContinue', { provider: p.name }) }}</a>
  </div>
</template>

<style scoped>
.oauth {
  display: grid;
  gap: 0.75rem;
  margin-top: 1.5rem;
}

.oauth__sep {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  margin: 0;
  color: var(--color-muted);
  font-size: 0.9rem;
}

.oauth__sep::before,
.oauth__sep::after {
  content: '';
  flex: 1;
  border-top: 1px solid var(--color-border);
}

.oauth__btn {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 2.75rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  color: var(--color-text);
  font-weight: 600;
  text-decoration: none;
}

.oauth__btn:hover {
  background: var(--color-surface-2);
}
</style>
