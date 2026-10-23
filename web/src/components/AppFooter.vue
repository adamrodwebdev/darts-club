<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { LANDING } from '@/content/landing'
import RockHand from './motion/RockHand.vue'
import MotionToggle from './preferences/MotionToggle.vue'

const { t } = useI18n()
</script>

<template>
  <footer class="footer">
    <div class="footer__inner">
      <p class="footer__brand">
        <img :src="LANDING.logo" width="32" height="32" alt="" loading="lazy" />
        {{ LANDING.name }} <span class="muted">· {{ t('footer.association') }}</span>
      </p>
      <nav :aria-label="t('footer.nav')">
        <ul>
          <li><a :href="LANDING.facebook" rel="noopener noreferrer external" target="_blank">Facebook<span class="visually-hidden"> {{ t('common.newTab') }}</span></a></li>
          <li><RouterLink to="/mentions-legales">{{ t('footer.legalNotice') }}</RouterLink></li>
          <li><RouterLink to="/confidentialite">{{ t('footer.privacy') }}</RouterLink></li>
        </ul>
      </nav>
      <MotionToggle />
      <RockHand class="footer__hand" />
    </div>
  </footer>
</template>

<style scoped>
.footer {
  border-top: 1px solid var(--color-border);
  background: var(--color-surface);
}

/* Accueil « manche de guitare » : le bas de page est un point d'arrêt, sinon la
   dernière corde ramènerait la page vers le haut et le pied de page serait inaccessible */
:global(html:has(.fretboard)) .footer {
  scroll-snap-align: end;
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

/* Calée sur le bord bas du pied de page : la main en surgit */
.footer__hand {
  align-self: flex-end;
  margin-inline-start: auto;
  margin-block-end: -1.25rem;
}

a {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
}
</style>
