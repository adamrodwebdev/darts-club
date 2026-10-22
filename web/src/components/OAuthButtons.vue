<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useServices } from '@/services'
import type { OAuthProvider } from '@/services/AuthService'

const services = useServices()

const { t } = useI18n()
const NAMES: Record<OAuthProvider, string> = { google: 'Google', discord: 'Discord' }

/** Seuls les fournisseurs configurés sur le serveur sont proposés */
const providers = ref<OAuthProvider[]>([])
onMounted(async () => {
  providers.value = await services.auth.oauthProviders()
})
</script>

<template>
  <div v-if="providers.length" class="oauth">
    <p class="oauth__sep"><span>{{ t('auth.oauthSeparator') }}</span></p>
    <!-- Liens classiques : la redirection OAuth est une navigation complète -->
    <a v-for="p in providers" :key="p" class="oauth__btn" :href="services.auth.oauthUrl(p)">{{ t('auth.oauthContinue', { provider: NAMES[p] }) }}</a>
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
