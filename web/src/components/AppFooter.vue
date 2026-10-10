<script setup lang="ts">
import { RouterLink } from 'vue-router'
</script>

<template>
  <footer class="footer">
    <div class="footer__inner">
      <p class="footer__brand">Darts Club <span class="muted">· association loi 1901</span></p>
      <nav aria-label="Informations légales">
        <ul>
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
  margin: 0;
  font-weight: 800;
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
