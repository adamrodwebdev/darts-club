<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
</script>

<template>
  <section class="home">
    <h1>Darts Club</h1>
    <p>Le site du club arrive bientôt.</p>
    <RouterLink v-if="auth.isAuthenticated" to="/messages" class="cta">Mes messages</RouterLink>
    <RouterLink v-else to="/inscription" class="cta">Rejoindre le club</RouterLink>
  </section>
</template>

<style scoped>
.home {
  display: grid;
  place-content: center;
  justify-items: center;
  gap: 1rem;
  min-height: calc(100vh - 4rem);
  padding: 1rem var(--gutter);
  text-align: center;
}

h1 {
  margin: 0;
  font-size: clamp(2.5rem, 8vw, 5rem);
  letter-spacing: -0.02em;
}

p {
  margin: 0;
  color: var(--color-muted);
}

.cta {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  padding: 0 1.4rem;
  border-radius: var(--radius);
  background: var(--color-accent);
  color: var(--color-on-accent);
  font-weight: 700;
  text-decoration: none;
}
</style>
