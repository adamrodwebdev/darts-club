<script setup lang="ts">
import { RouterLink } from 'vue-router'
import { LANDING } from '@/content/landing'
</script>

<template>
  <footer class="footer">
    <div class="footer__inner">
      <p class="footer__brand">
        <img :src="LANDING.logo" width="32" height="32" alt="" loading="lazy" />
        {{ LANDING.name }} <span class="muted">· association loi 1901</span>
      </p>
      <nav aria-label="Liens du pied de page">
        <ul>
          <li><a :href="LANDING.facebook" rel="noopener noreferrer external" target="_blank">Facebook<span class="visually-hidden"> (nouvel onglet)</span></a></li>
          <li><RouterLink to="/mentions-legales">Mentions légales</RouterLink></li>
          <li><RouterLink to="/confidentialite">Confidentialité</RouterLink></li>
          <li><a href="https://github.com/adamrodwebdev/darts-club" rel="noopener noreferrer external" target="_blank">Code source</a></li>
        </ul>
      </nav>
    </div>
  </footer>
</template>

<style scoped>
.footer {
  border-top: 1px solid var(--color-border);
  background: var(--color-surface);
}

.footer__inner {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem 1.5rem;
  max-width: var(--max-width);
  margin: 0 auto;
  padding: 1.25rem var(--gutter);
}

.footer__brand {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0;
  font-weight: 800;
}

.footer__brand img {
  border-radius: 50%;
}

ul {
  display: flex;
  flex-wrap: wrap;
  gap: 0 1rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

a {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
}
</style>
