<script setup lang="ts">
import type { QrPosterContent } from './QrPosterContent'

/**
 * Affiche QR à poser sur les tables pendant les soirées. Tailles relatives à sa propre largeur
 * (unités de conteneur) : le même dessin sert à l'aperçu, au format A5 et au format A4.
 */
withDefaults(defineProps<{ content: QrPosterContent; ink?: 'light' | 'dark'; size?: 'a5' | 'a4' }>(), { ink: 'light', size: 'a5' })
</script>

<template>
  <article class="qr-poster" :class="[`qr-poster--${ink}`, `qr-poster--${size}`, { 'qr-poster--evening': content.eyebrow }]">
    <div class="sheet">
      <img class="logo" src="/logo.svg" alt="" width="104" height="104" />
      <p v-if="content.eyebrow" class="eyebrow">{{ content.eyebrow }}</p>
      <h2 class="heading">{{ content.heading }}</h2>
      <p class="subheading">{{ content.subheading }}</p>
      <div class="frame">
        <img class="qr" :src="content.qrSrc" :alt="content.qrAlt" width="600" height="600" />
        <span class="badge"><img src="/logo.svg" alt="" width="56" height="56" /></span>
      </div>
      <p class="primary">{{ content.primary }}</p>
      <p class="secondary">{{ content.secondary }}</p>
      <p class="footer">{{ content.footer }}</p>
    </div>
  </article>
</template>

<style scoped>
/*
 * Proportions d'une feuille A (1 : √2) ; tailles en % de la largeur de l'affiche (cqw), d'après la
 * maquette A5. L'affiche est le conteneur, sans marge intérieure : la feuille la porte, mesurée sur
 * toute la largeur (des cqw sur le conteneur lui-même se mesureraient sur l'écran).
 */
.qr-poster {
  --paper: var(--poster-paper);
  --ink: var(--poster-ink);
  --muted: var(--poster-ink-muted);
  --eyebrow: var(--poster-accent);
  container: qr-poster / inline-size;
  width: 100%;
  aspect-ratio: 1 / 1.4142;
  overflow: hidden;
  background: var(--paper);
  color: var(--ink);
  font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
  text-align: center;
  print-color-adjust: exact;
  -webkit-print-color-adjust: exact;
}

.sheet {
  box-sizing: border-box;
  display: flex;
  flex-direction: column;
  align-items: center;
  height: 100%;
  padding: 7.5cqw 8.6cqw 6.5cqw;
}

.qr-poster--dark {
  --paper: var(--poster-night);
  --ink: var(--poster-night-ink);
  --muted: var(--poster-night-muted);
  --eyebrow: var(--poster-gold);
}

/*
 * Soirée : une ligne de plus (son nom) et une date qui peut tenir sur deux lignes ; logo, titre et
 * QR code un peu plus petits pour que tout tienne dans la feuille.
 */
.qr-poster--evening .logo {
  width: 12cqw;
}

.qr-poster--evening .heading {
  font-size: 8cqw;
}

.qr-poster--evening .qr {
  width: 44cqw;
}

/* Le contenu se règle sur la largeur de l'affiche */
.sheet > * {
  margin: 0;
}

.logo {
  width: 16cqw;
  height: auto;
}

.qr-poster--dark .logo {
  border-radius: 50%;
  background: var(--poster-night-ink);
}

.eyebrow {
  margin-top: 4cqw;
  color: var(--eyebrow);
  font-size: 2.7cqw;
  font-weight: 800;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.heading {
  margin-top: 2.6cqw;
  font-size: 9cqw;
  font-weight: 900;
  line-height: 1.05;
  letter-spacing: -0.01em;
  text-wrap: balance;
}

.subheading {
  margin-top: 1.8cqw;
  color: var(--muted);
  font-size: 3.4cqw;
  font-weight: 600;
}

.frame {
  position: relative;
  margin-top: 4.6cqw;
  padding: 3.6cqw;
  border: 0.54cqw solid var(--ink);
  border-radius: 3.9cqw;
  background: var(--poster-paper);
}

/* Fond noir : le QR code reste sur blanc (meilleure lecture), cerné de rouge */
.qr-poster--dark .frame {
  border-color: var(--poster-paper);
  box-shadow: 0 0 0 0.72cqw var(--poster-accent);
}

.qr {
  display: block;
  width: 48cqw;
  height: auto;
}

.badge {
  position: absolute;
  top: 50%;
  left: 50%;
  display: grid;
  place-items: center;
  width: 11.8cqw;
  height: 11.8cqw;
  border-radius: 50%;
  background: var(--poster-paper);
  transform: translate(-50%, -50%);
}

.badge img {
  width: 10cqw;
  height: auto;
}

.primary {
  margin-top: 4cqw;
  font-size: 5.6cqw;
  font-weight: 900;
  overflow-wrap: anywhere;
}

.secondary {
  margin-top: 1.1cqw;
  color: var(--muted);
  font-size: 2.9cqw;
  overflow-wrap: anywhere;
}

.footer {
  margin-top: auto;
  padding-top: 2cqw;
  color: var(--muted);
  font-size: 2.15cqw;
}
</style>

<!-- Impression : seule l'affiche sort, à la taille du papier choisi (pages nommées, sans effet ailleurs) -->
<style>
@page qr-poster-a5 {
  size: A5 portrait;
  margin: 0;
}

@page qr-poster-a4 {
  size: A4 portrait;
  margin: 0;
}

@media print {
  html[data-print='qr-poster'],
  html[data-print='qr-poster'] body {
    background: #fff;
  }

  html[data-print='qr-poster'] body *:not(:has(.qr-poster), .qr-poster, .qr-poster *) {
    display: none !important;
  }

  html[data-print='qr-poster'] body *:has(.qr-poster) {
    display: block !important;
    width: auto !important;
    height: auto !important;
    min-height: 0 !important;
    max-width: none !important;
    margin: 0 !important;
    padding: 0 !important;
    border: 0 !important;
    background: none !important;
    box-shadow: none !important;
  }

  html[data-print='qr-poster'] .qr-poster {
    border-radius: 0 !important;
    box-shadow: none !important;
    break-inside: avoid;
  }

  html[data-print='qr-poster'] .qr-poster--a5 {
    width: 148mm;
    page: qr-poster-a5;
  }

  html[data-print='qr-poster'] .qr-poster--a4 {
    width: 210mm;
    page: qr-poster-a4;
  }
}
</style>
